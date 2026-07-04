<?php

namespace App\Modules\BoK\Filament\Resources\BokResource\Pages;

use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
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
        return ['import_kurikulum_id'];
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return [
            Select::make('import_kurikulum_id')
                ->label('Kurikulum')
                ->options(KurikulumTerpilih::options())
                ->searchable()
                ->required()
                ->default(fn (): ?string => KurikulumTerpilih::currentId()),
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
        $unitId = $this->unitIdDariKonteks($context);

        if (blank($unitId)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih kurikulum akademik terlebih dahulu.'];
        }

        if ($data['kode_cpl'] !== '') {
            $cplAda = Cpl::query()
                ->where('academic_unit_id', $unitId)
                ->where('kode', $data['kode_cpl'])
                ->exists();

            if (! $cplAda) {
                return ['status' => 'invalid', 'keterangan' => "CPL '{$data['kode_cpl']}' tidak ditemukan pada unit ini."];
            }
        }

        $existing = Bok::query()
            ->where('academic_unit_id', $unitId)
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
        $unitId = $this->unitIdDariKonteks($context);
        $bok = Bok::query()->create([
            'academic_unit_id' => $unitId,
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
            ->where('academic_unit_id', $this->unitIdDariKonteks($context))
            ->where('kode', $data['kode_cpl'])
            ->first();

        if ($cpl) {
            CplBok::query()->firstOrCreate(
                ['cpl_id' => $cpl->id, 'bok_id' => $bok->id],
                ['id' => (string) Str::uuid()],
            );
        }
    }

    /**
     * Unit pemilik diturunkan dari kurikulum terpilih pada konteks impor.
     *
     * @param  array<string, mixed>  $context
     */
    protected function unitIdDariKonteks(array $context): ?string
    {
        $kurikulumId = $context['import_kurikulum_id'] ?? null;

        if (blank($kurikulumId)) {
            return null;
        }

        return Kurikulum::query()->whereKey($kurikulumId)->value('academic_unit_id');
    }
}
