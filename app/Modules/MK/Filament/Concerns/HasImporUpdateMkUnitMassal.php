<?php

namespace App\Modules\MK\Filament\Concerns;

use App\Modules\Kurikulum\Filament\Support\BannerKurikulumDikerjakan;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Services\MkUnitUpdateMassalService;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;

trait HasImporUpdateMkUnitMassal
{
    use HasImporMassal;

    protected function makeUpdateMkUnitMassalAction(): Action
    {
        return $this->makeImporMassalAction()
            ->label('Update kode & semester')
            ->icon(Heroicon::OutlinedArrowPath);
    }

    protected function importModalHeading(): string
    {
        return 'Update kode & semester massal';
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return ['import_kurikulum_id'];
    }

    /**
     * Kurikulum sasaran tidak dipilih manual: selalu kurikulum prodi yang
     * sedang dikerjakan, ditegaskan lewat banner identitas di atas modal.
     *
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return [
            BannerKurikulumDikerjakan::placeholder(
                'Seluruh baris di bawah akan memperbarui kode dan semester penawaran MK pada kurikulum ini.',
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function normalisasiImportContext(array $context): array
    {
        $context['import_kurikulum_id'] = BannerKurikulumDikerjakan::kurikulumProdi()?->id;

        return $context;
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'nama', 'label' => 'mata kuliah', 'wajib' => true],
            ['key' => 'kode', 'label' => 'kode', 'wajib' => true],
            ['key' => 'semester_ke', 'label' => 'semester ke-', 'wajib' => true],
        ];
    }

    protected function importInstructionsExtra(): array
    {
        return [
            'Satu baris = satu penawaran MK pada kurikulum prodi yang dikerjakan (lihat banner di atas).',
            'Nama mata kuliah harus persis sama dengan penawaran yang sudah ada (adaptasi MK).',
            'Kode dan semester ke- wajib diisi; semester harus angka 1–14.',
            'Pratinjau menandai baris siap diperbarui, duplikat (nilai sudah sama), atau invalid.',
            'Duplikat: kode dan semester sudah sama dengan data lama — lewati atau timpa seperti impor massal.',
        ];
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            "Kalkulus\tMTK101\t3",
            "Pendidikan Pancasila\tPP101\t2",
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Gunakan tombol Salin mata kuliah untuk mengekspor daftar awal ke Excel.';
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function resolveImportRow(array $data, array $context): array
    {
        return app(MkUnitUpdateMassalService::class)->resolveBaris($data, $context);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function createImportRow(array $data, array $context): void
    {
        $service = app(MkUnitUpdateMassalService::class);
        $unitId = $service->unitIdDariKonteks($context);
        $kurikulumId = (string) ($context['import_kurikulum_id'] ?? '');
        $mkUnit = $service->cariPenawaran((string) $unitId, $kurikulumId, $data['nama']);

        if ($mkUnit) {
            $service->perbaruiPenawaran($mkUnit, $data);
        }
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $mkUnit = MkUnit::query()->findOrFail($existingId);
        app(MkUnitUpdateMassalService::class)->perbaruiPenawaran($mkUnit, $data);
    }
}
