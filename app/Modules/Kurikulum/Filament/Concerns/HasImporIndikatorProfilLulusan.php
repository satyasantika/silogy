<?php

namespace App\Modules\Kurikulum\Filament\Concerns;

use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Support\Filament\Concerns\HasImporMassal;

/**
 * Impor massal indikator profil lulusan via copy-paste pada halaman edit profil.
 */
trait HasImporIndikatorProfilLulusan
{
    use HasImporMassal {
        runImport as protected runImportMassal;
    }

    protected function importModalHeading(): string
    {
        return 'Impor indikator profil lulusan';
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'nama', 'label' => 'nama indikator', 'wajib' => true],
        ];
    }

    protected function importInstructionsExtra(): array
    {
        return [
            'Satu baris = satu indikator; cukup isi kolom nama.',
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Indikator ditambahkan ke profil lulusan yang sedang diedit. Satu baris = satu indikator.';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            'Mampu berpikir kritis',
            'Mampu berkomunikasi',
        ];
    }

    protected function profilLulusanUntukImpor(): ProfilLulusan
    {
        /** @var ProfilLulusan $profil */
        $profil = $this->getRecord();

        return $profil;
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        $profil = $this->profilLulusanUntukImpor();
        $namaNormal = mb_strtolower(trim($data['nama']));

        $existing = ProfilIndikator::query()
            ->where('profil_id', $profil->id)
            ->whereRaw('LOWER(TRIM(nama)) = ?', [$namaNormal])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Nama indikator sudah ada pada profil ini.',
                'existing_id' => $existing->id,
                'dedup' => $namaNormal,
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => $namaNormal];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $profil = $this->profilLulusanUntukImpor();
        $urutan = (int) ProfilIndikator::query()
            ->where('profil_id', $profil->id)
            ->max('urutan');

        ProfilIndikator::query()->create([
            'profil_id' => $profil->id,
            'nama' => $data['nama'],
            'deskripsi' => null,
            'urutan' => $urutan + 1,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $indikator = ProfilIndikator::query()->findOrFail($existingId);

        $indikator->update([
            'nama' => $data['nama'],
        ]);
    }

    protected function runImport(string $raw, string $modeDuplikat, array $context = []): void
    {
        $this->runImportMassal($raw, $modeDuplikat, $context);

        $this->record->refresh();
        $this->record->load('indikators');
        $this->refreshFormData(['indikators']);
    }
}
