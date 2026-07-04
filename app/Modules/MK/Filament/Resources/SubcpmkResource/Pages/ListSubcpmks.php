<?php

namespace App\Modules\MK\Filament\Resources\SubcpmkResource\Pages;

use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Filament\Resources\SubcpmkResource;
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
        return ['import_mk_cpmk_id', 'import_semester_id'];
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        $mkCpmkOptions = SubcpmkResource::mkCpmkOptions();

        return [
            Select::make('import_mk_cpmk_id')
                ->label('CPMK (via CPL–MK)')
                ->options($mkCpmkOptions)
                ->searchable()
                ->required()
                ->default(count($mkCpmkOptions) === 1 ? array_key_first($mkCpmkOptions) : null),
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
            ['key' => 'kode', 'label' => 'kode', 'wajib' => true],
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => true],
            ['key' => 'bobot', 'label' => 'bobot (%)', 'wajib' => false],
            ['key' => 'indikator', 'label' => 'indikator', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Seluruh baris diimpor sebagai Sub-CPMK dari CPMK dan semester yang dipilih di atas.';
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if (blank($context['import_mk_cpmk_id'] ?? null) || blank($context['import_semester_id'] ?? null)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih CPMK dan semester terlebih dahulu.'];
        }

        if ($data['bobot'] !== '' && ! is_numeric($data['bobot'])) {
            return ['status' => 'invalid', 'keterangan' => 'Bobot harus berupa angka.'];
        }

        $existing = Subcpmk::query()
            ->where('mk_cpmk_id', $context['import_mk_cpmk_id'])
            ->where('semester_id', $context['import_semester_id'])
            ->where('kode', $data['kode'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode Sub-CPMK sudah ada pada CPMK dan semester ini.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['kode']),
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['kode'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        Subcpmk::query()->create([
            'mk_cpmk_id' => $context['import_mk_cpmk_id'],
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
}
