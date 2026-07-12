<?php

namespace App\Modules\MK\Filament\Resources\SubcpmkResource\Pages;

use App\Modules\MK\Filament\Resources\SubcpmkResource;
use App\Modules\MK\Filament\Support\Concerns\HasImporMkSemesterKonteks;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Services\SubcpmkKompetensiParser;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;

class ListSubcpmks extends ListRecords
{
    use HasImporMassal;
    use HasImporMkSemesterKonteks;

    protected static string $resource = SubcpmkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => SubcpmkResource::canCreate()),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor Sub-CPMK massal';
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return $this->importMkSemesterContextKeys();
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return $this->importMkSemesterContextComponents(
            SubcpmkResource::scopedKoordinatorMkOptions(),
        );
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode_cpmk', 'label' => 'kode CPMK', 'wajib' => true],
            ['key' => 'kode', 'label' => 'kode sub-CPMK', 'wajib' => true],
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => true],
            ['key' => 'kompetensi', 'label' => 'kompetensi (C/A/P)', 'wajib' => false],
            ['key' => 'indikator', 'label' => 'indikator', 'wajib' => false],
            ['key' => 'evaluasi', 'label' => 'evaluasi', 'wajib' => false],
            ['key' => 'bobot', 'label' => 'bobot (%)', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Kode CPMK harus sudah ada pada mata kuliah yang dipilih di atas dan telah dipetakan ke CPL–MK. '
            .'Semester (Koordinator MK) diambil dari master semester. '
            .'Kompetensi opsional: taksonomi Bloom dipisah koma, contoh C3,A2,P2.';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            'CPMK-01|SUB-01|Menjelaskan definisi|C3,A2,P2|Indikator A|UTS dan kuis|50',
            'CPMK-01|SUB-02|Menerapkan rumus|C4|||50',
        ];
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        $validasiKonteks = $this->validasiKonteksImporMkSemester($context);

        if ($validasiKonteks !== null) {
            return $validasiKonteks;
        }

        $context = $this->normalizeImportContext($context);

        if ($data['bobot'] !== '' && ! is_numeric($data['bobot'])) {
            return ['status' => 'invalid', 'keterangan' => 'Bobot harus berupa angka.'];
        }

        if (filled($data['kompetensi'] ?? null)) {
            $validasiKompetensi = SubcpmkKompetensiParser::validasi($data['kompetensi']);

            if (! $validasiKompetensi['valid']) {
                return ['status' => 'invalid', 'keterangan' => $validasiKompetensi['keterangan']];
            }
        }

        $mkCpmk = $this->mkCpmkDariKode($data['kode_cpmk'], $context);

        if ($mkCpmk === null) {
            return ['status' => 'invalid', 'keterangan' => "CPMK '{$data['kode_cpmk']}' tidak ditemukan pada MK ini atau belum dipetakan ke CPL–MK."];
        }

        $existing = Subcpmk::query()
            ->where('mk_cpmk_id', $mkCpmk->id)
            ->where('semester_id', $context['import_semester_id'])
            ->where('kode', $data['kode'])
            ->first();

        $dedup = mb_strtolower($data['kode_cpmk'].'/'.$data['kode']);

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode Sub-CPMK sudah ada pada CPMK dan semester ini.',
                'existing_id' => $existing->id,
                'dedup' => $dedup,
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => $dedup];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $context = $this->normalizeImportContext($context);
        $mkCpmk = $this->mkCpmkDariKode($data['kode_cpmk'], $context);
        $bloom = filled($data['kompetensi'] ?? null)
            ? SubcpmkKompetensiParser::parse($data['kompetensi'])
            : [];

        Subcpmk::query()->create([
            'mk_cpmk_id' => $mkCpmk?->id,
            'semester_id' => $context['import_semester_id'],
            'kode' => $data['kode'],
            'deskripsi' => $data['deskripsi'],
            'bobot' => $data['bobot'] !== '' ? (float) $data['bobot'] : null,
            'indikator' => $data['indikator'] ?: null,
            'evaluasi' => $data['evaluasi'] ?: null,
            ...$bloom,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $subcpmk = Subcpmk::query()->findOrFail($existingId);
        $payload = [
            'deskripsi' => $data['deskripsi'],
            'indikator' => $data['indikator'] ?: $subcpmk->indikator,
            'evaluasi' => filled($data['evaluasi'] ?? null) ? $data['evaluasi'] : $subcpmk->evaluasi,
        ];

        // Bobot hasil impor hanya berlaku selama Sub-CPMK ini belum
        // berinteraksi dengan komponen penilaian (asesmen). Begitu ada
        // interaksi, bobot dikelola otomatis (lihat SubcpmkKomponenPenilaianObserver)
        // dan tidak boleh ditimpa oleh impor massal.
        $sudahBerinteraksi = SubcpmkKomponenPenilaian::query()
            ->where('subcpmk_id', $existingId)
            ->exists();

        if (! $sudahBerinteraksi) {
            $payload['bobot'] = $data['bobot'] !== '' ? (float) $data['bobot'] : $subcpmk->bobot;
        }

        if (filled($data['kompetensi'] ?? null)) {
            $payload = [...$payload, ...SubcpmkKompetensiParser::parse($data['kompetensi'])];
        }

        $subcpmk->update($payload);
    }

    /**
     * Pemetaan CPL–MK milik CPMK berkode tersebut pada MK konteks.
     *
     * @param  array<string, mixed>  $context
     */
    protected function mkCpmkDariKode(string $kodeCpmk, array $context): ?MkCpmk
    {
        $cpmk = Cpmk::query()
            ->where('mk_id', $context['import_mk_id'] ?? null)
            ->where('kode', $kodeCpmk)
            ->first();

        if (! $cpmk) {
            return null;
        }

        return MkCpmk::query()->where('cpmk_id', $cpmk->id)->first();
    }
}
