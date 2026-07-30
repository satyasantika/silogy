<?php

namespace App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages;

use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Str;

class ListAcademicUnits extends ListRecords
{
    use HasImporMassal {
        HasImporMassal::parseImportRaw as private traitParseImportRaw;
    }

    protected static string $resource = AcademicUnitResource::class;

    /**
     * kode => type unit yang di-resolve "baru" pada baris SEBELUMNYA di
     * tempelan yang sama — supaya baris berikutnya bisa berinduk ke unit
     * yang baru dibuat di baris lebih awal, bukan cuma unit yang sudah ada
     * di database sebelum impor ini berjalan.
     *
     * Direset eksplisit di setiap pemanggilan parseImportRaw() (lihat
     * override di bawah) — bukan cuma mengandalkan properti protected
     * "kosong per request Livewire baru", karena satu request BISA memicu
     * parseImportRaw() lebih dari sekali (mis. sekali untuk cek visibilitas
     * tombol submit modal via importMassalPratinjauSiap(), sekali lagi di
     * dalam runImport()) — tanpa reset eksplisit, pendaftaran dari pass
     * pertama bisa "bocor" ke pass kedua dan meloloskan referensi mundur
     * (baris berinduk ke baris SESUDAHNYA) yang seharusnya tetap invalid.
     *
     * @var array<string, string>
     */
    protected array $importUnitBaruDalamBatch = [];

    public function parseImportRaw(string $raw, array $context = []): array
    {
        $this->importUnitBaruDalamBatch = [];

        return $this->traitParseImportRaw($raw, $context);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => AcademicUnitResource::canCreate()),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor unit akademik massal';
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'jenis', 'label' => 'jenis', 'wajib' => true],
            ['key' => 'kode', 'label' => 'kode', 'wajib' => true],
            ['key' => 'jenjang', 'label' => 'jenjang', 'wajib' => false],
            ['key' => 'nama', 'label' => 'nama', 'wajib' => true],
            ['key' => 'kode_induk', 'label' => 'kode unit induk', 'wajib' => false],
            ['key' => 'singkatan', 'label' => 'singkatan', 'wajib' => false],
            ['key' => 'status', 'label' => 'status', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Jenis: universitas, fakultas, jurusan, atau prodi. Kode unit induk wajib untuk selain universitas '
            .'(prodi boleh berinduk ke jurusan atau langsung fakultas) — boleh merujuk unit yang baru dibuat di '
            .'baris lebih awal pada tempelan yang sama. Jenjang (D3/D4/S1/S2/S3/Profesi) wajib diisi khusus untuk '
            .'jenis prodi, kosongkan untuk jenis lain. Status: draft/aktif/nonaktif (default aktif).';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "fakultas\t10\t\tFakultas Agama Islam\tUNSIL\tFAI\taktif",
            "prodi\t1002\tS1\tEkonomi Syariah\t10\tS1-eksyar\taktif",
        ];
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        $type = $this->normalizeType($data['jenis']);

        if ($type === null) {
            return ['status' => 'invalid', 'keterangan' => 'Jenis harus universitas, fakultas, jurusan, atau prodi.'];
        }

        $status = $data['status'] === '' ? 'aktif' : Str::lower($data['status']);

        if (! array_key_exists($status, AcademicUnitResource::statusOptions())) {
            return ['status' => 'invalid', 'keterangan' => 'Status harus draft, aktif, atau nonaktif.'];
        }

        if ($type === 'study_program') {
            if ($data['jenjang'] === '') {
                return ['status' => 'invalid', 'keterangan' => 'Jenjang wajib diisi untuk unit berjenis prodi.'];
            }

            if ($this->normalizeJenjang($data['jenjang']) === null) {
                return [
                    'status' => 'invalid',
                    'keterangan' => 'Jenjang harus salah satu dari: '.implode(', ', array_keys(AcademicUnitResource::jenjangOptions())).'.',
                ];
            }
        }

        $parentTypes = AcademicUnitResource::parentTypesFor($type);

        if ($parentTypes !== [] && $data['kode_induk'] === '') {
            return ['status' => 'invalid', 'keterangan' => 'Kode unit induk wajib untuk jenis ini.'];
        }

        if ($parentTypes !== []) {
            $parent = AcademicUnit::query()->where('code', $data['kode_induk'])->first();
            $parentType = $parent?->type ?? ($this->importUnitBaruDalamBatch[$data['kode_induk']] ?? null);

            if ($parentType === null) {
                return ['status' => 'invalid', 'keterangan' => "Unit induk dengan kode '{$data['kode_induk']}' tidak ditemukan."];
            }

            if (! in_array($parentType, $parentTypes, true)) {
                return ['status' => 'invalid', 'keterangan' => 'Jenis unit induk tidak sesuai (harus '.implode(' atau ', $parentTypes).').'];
            }
        }

        $existing = AcademicUnit::query()->where('code', $data['kode'])->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode unit sudah terdaftar.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['kode']),
            ];
        }

        // Catat unit ini supaya baris SESUDAHNYA pada tempelan yang sama
        // bisa berinduk ke sini walau belum tersimpan di database.
        $this->importUnitBaruDalamBatch[$data['kode']] = $type;

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['kode'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $type = $this->normalizeType($data['jenis']);
        $parent = $data['kode_induk'] !== ''
            ? AcademicUnit::query()->where('code', $data['kode_induk'])->first()
            : null;

        AcademicUnit::query()->create([
            'type' => $type,
            'parent_id' => $parent?->id,
            'code' => $data['kode'],
            'jenjang' => $this->normalizeJenjang($data['jenjang']),
            'nama' => $data['nama'],
            'singkatan' => $data['singkatan'] ?: null,
            'status' => $data['status'] === '' ? 'aktif' : Str::lower($data['status']),
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $unit = AcademicUnit::query()->findOrFail($existingId);

        $unit->update([
            'nama' => $data['nama'],
            'jenjang' => $this->normalizeJenjang($data['jenjang']),
            'singkatan' => $data['singkatan'] ?: $unit->singkatan,
            'status' => $data['status'] === '' ? $unit->status : Str::lower($data['status']),
        ]);
    }

    protected function normalizeType(string $jenis): ?string
    {
        return match (Str::lower(trim($jenis))) {
            'universitas', 'university' => 'university',
            'fakultas', 'faculty' => 'faculty',
            'jurusan', 'department' => 'department',
            'prodi', 'program studi', 'study_program' => 'study_program',
            default => null,
        };
    }

    protected function normalizeJenjang(string $jenjang): ?string
    {
        if ($jenjang === '') {
            return null;
        }

        return collect(AcademicUnitResource::jenjangOptions())
            ->keys()
            ->first(fn (string $option): bool => Str::lower($option) === Str::lower(trim($jenjang)));
    }
}
