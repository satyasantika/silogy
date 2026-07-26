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
    use HasImporMassal;

    protected static string $resource = AcademicUnitResource::class;

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
            ['key' => 'nama', 'label' => 'nama', 'wajib' => true],
            ['key' => 'kode_induk', 'label' => 'kode unit induk', 'wajib' => false],
            ['key' => 'singkatan', 'label' => 'singkatan', 'wajib' => false],
            ['key' => 'status', 'label' => 'status', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Jenis: universitas, fakultas, jurusan, atau prodi. Kode unit induk wajib untuk selain universitas '
            .'(prodi boleh berinduk ke jurusan atau langsung fakultas). Status: draft/aktif/nonaktif (default aktif).';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "fakultas\tFT\tFakultas Teknik\tUNSIL\tFT\taktif",
            "prodi\tPTI\tProdi Pendidikan Informatika\tFT\t\taktif",
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

        $parentTypes = AcademicUnitResource::parentTypesFor($type);

        if ($parentTypes !== [] && $data['kode_induk'] === '') {
            return ['status' => 'invalid', 'keterangan' => 'Kode unit induk wajib untuk jenis ini.'];
        }

        if ($parentTypes !== []) {
            $parent = AcademicUnit::query()->where('code', $data['kode_induk'])->first();

            if (! $parent) {
                return ['status' => 'invalid', 'keterangan' => "Unit induk dengan kode '{$data['kode_induk']}' tidak ditemukan."];
            }

            if (! in_array($parent->type, $parentTypes, true)) {
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
}
