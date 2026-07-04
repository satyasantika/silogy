<?php

namespace App\Modules\BoK\Filament\Resources\BokResource\Pages;

use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

class ListBoks extends ListRecords
{
    use HasImporMassal;

    protected static string $resource = BokResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => BokResource::canCreate()),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor bahan kajian (BoK) massal';
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return ['import_unit_id'];
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        $unitIds = BokResource::scopedTimKurikulumUnitIds();

        return [
            Select::make('import_unit_id')
                ->label('Unit akademik pemilik BoK')
                ->options(BokResource::timKurikulumUnitOptions())
                ->searchable()
                ->required()
                ->default($unitIds->count() === 1 ? $unitIds->first() : null),
        ];
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode', 'label' => 'kode', 'wajib' => true],
            ['key' => 'nama', 'label' => 'nama', 'wajib' => true],
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => false],
            ['key' => 'kode_cpl', 'label' => 'kode CPL terpetakan', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Bila kode CPL diisi, BoK langsung dipetakan ke CPL tersebut (CPL harus sudah ada di unit yang sama).';
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if (blank($context['import_unit_id'] ?? null)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih unit akademik terlebih dahulu.'];
        }

        if ($data['kode_cpl'] !== '') {
            $cplAda = Cpl::query()
                ->where('academic_unit_id', $context['import_unit_id'])
                ->where('kode', $data['kode_cpl'])
                ->exists();

            if (! $cplAda) {
                return ['status' => 'invalid', 'keterangan' => "CPL '{$data['kode_cpl']}' tidak ditemukan pada unit ini."];
            }
        }

        $existing = Bok::query()
            ->where('academic_unit_id', $context['import_unit_id'])
            ->where('kode', $data['kode'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode BoK sudah ada pada unit ini.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['kode']),
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['kode'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $bok = Bok::query()->create([
            'academic_unit_id' => $context['import_unit_id'],
            'kode' => $data['kode'],
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'] ?: null,
        ]);

        $this->petakanCpl($bok, $data, $context);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $bok = Bok::query()->findOrFail($existingId);

        $bok->update([
            'nama' => $data['nama'],
            'deskripsi' => $data['deskripsi'] ?: $bok->deskripsi,
        ]);

        $this->petakanCpl($bok, $data, $context);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function petakanCpl(Bok $bok, array $data, array $context): void
    {
        if ($data['kode_cpl'] === '') {
            return;
        }

        $cpl = Cpl::query()
            ->where('academic_unit_id', $context['import_unit_id'])
            ->where('kode', $data['kode_cpl'])
            ->first();

        if ($cpl) {
            CplBok::query()->firstOrCreate(
                ['cpl_id' => $cpl->id, 'bok_id' => $bok->id],
                ['id' => (string) Str::uuid()],
            );
        }
    }
}
