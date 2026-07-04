<?php

namespace App\Modules\MK\Filament\Resources\CpmkResource\Pages;

use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Models\Cpmk;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;

class ListCpmks extends ListRecords
{
    use HasImporMassal;

    protected static string $resource = CpmkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => CpmkResource::canCreate()),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor CPMK massal';
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return ['import_mk_id'];
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        $mkOptions = CpmkResource::scopedKoordinatorMkOptions();

        return [
            Select::make('import_mk_id')
                ->label('Mata kuliah')
                ->options($mkOptions)
                ->searchable()
                ->required()
                ->default(count($mkOptions) === 1 ? array_key_first($mkOptions) : null),
        ];
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode', 'label' => 'kode', 'wajib' => true],
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => true],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Seluruh baris diimpor sebagai CPMK dari mata kuliah yang dipilih di atas.';
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if (blank($context['import_mk_id'] ?? null)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih mata kuliah terlebih dahulu.'];
        }

        $existing = Cpmk::query()
            ->where('mk_id', $context['import_mk_id'])
            ->where('kode', $data['kode'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode CPMK sudah ada pada MK ini.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['kode']),
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['kode'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        Cpmk::query()->create([
            'mk_id' => $context['import_mk_id'],
            'kode' => $data['kode'],
            'deskripsi' => $data['deskripsi'],
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        Cpmk::query()->findOrFail($existingId)->update([
            'deskripsi' => $data['deskripsi'],
        ]);
    }
}
