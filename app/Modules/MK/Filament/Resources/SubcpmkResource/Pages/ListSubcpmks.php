<?php

namespace App\Modules\MK\Filament\Resources\SubcpmkResource\Pages;

use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Filament\Resources\SubcpmkResource;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;

class ListSubcpmks extends ListRecords
{
    use HasImporMassal;

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
        return ['import_mk_id', 'import_semester_id'];
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        $mkOptions = SubcpmkResource::scopedKoordinatorMkOptions();

        return [
            Select::make('import_mk_id')
                ->label('Mata kuliah')
                ->options($mkOptions)
                ->searchable()
                ->required()
                ->default(count($mkOptions) === 1 ? array_key_first($mkOptions) : null),
            Select::make('import_semester_id')
                ->label('Semester')
                ->options(fn (): array => Semester::query()->orderBy('kode')->pluck('nama', 'id')->all())
                ->searchable()
                ->required()
                ->default(fn (): ?string => Semester::query()->where('status_aktif', true)->value('id')),
        ];
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode_cpmk', 'label' => 'kode CPMK', 'wajib' => true],
            ['key' => 'kode', 'label' => 'kode sub-CPMK', 'wajib' => true],
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => true],
            ['key' => 'bobot', 'label' => 'bobot (%)', 'wajib' => false],
            ['key' => 'indikator', 'label' => 'indikator', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Kode CPMK harus sudah ada pada mata kuliah yang dipilih di atas dan telah dipetakan ke CPL–MK.';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            'CPMK-01|SUB-01|Menjelaskan definisi|50|Indikator A',
            'CPMK-01|SUB-02|Menerapkan rumus|50|',
        ];
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if (blank($context['import_mk_id'] ?? null) || blank($context['import_semester_id'] ?? null)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih mata kuliah dan semester terlebih dahulu.'];
        }

        if ($data['bobot'] !== '' && ! is_numeric($data['bobot'])) {
            return ['status' => 'invalid', 'keterangan' => 'Bobot harus berupa angka.'];
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
        $mkCpmk = $this->mkCpmkDariKode($data['kode_cpmk'], $context);

        Subcpmk::query()->create([
            'mk_cpmk_id' => $mkCpmk?->id,
            'semester_id' => $context['import_semester_id'],
            'kode' => $data['kode'],
            'deskripsi' => $data['deskripsi'],
            'bobot' => $data['bobot'] !== '' ? (float) $data['bobot'] : null,
            'indikator' => $data['indikator'] ?: null,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $subcpmk = Subcpmk::query()->findOrFail($existingId);

        $subcpmk->update([
            'deskripsi' => $data['deskripsi'],
            'bobot' => $data['bobot'] !== '' ? (float) $data['bobot'] : $subcpmk->bobot,
            'indikator' => $data['indikator'] ?: $subcpmk->indikator,
        ]);
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
