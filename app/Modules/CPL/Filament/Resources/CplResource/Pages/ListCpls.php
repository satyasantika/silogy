<?php

namespace App\Modules\CPL\Filament\Resources\CplResource\Pages;

use App\Modules\CPL\Filament\Resources\CplResource;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

class ListCpls extends ListRecords
{
    use HasImporMassal;

    protected static string $resource = CplResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => CplResource::canCreate()),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor CPL massal';
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
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => true],
            ['key' => 'domain', 'label' => 'domain', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Domain: kognitif, afektif, psikomotorik, atau gabungan.';
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        $unitId = $this->unitIdDariKonteks($context);

        if (blank($unitId)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih kurikulum akademik terlebih dahulu.'];
        }

        if ($data['domain'] !== '' && ! in_array(Str::lower($data['domain']), ['kognitif', 'afektif', 'psikomotorik', 'gabungan'], true)) {
            return ['status' => 'invalid', 'keterangan' => 'Domain harus kognitif, afektif, psikomotorik, atau gabungan.'];
        }

        $existing = Cpl::query()
            ->where('academic_unit_id', $unitId)
            ->where('kode', $data['kode'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode CPL sudah ada pada unit ini.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['kode']),
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['kode'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $unitId = $this->unitIdDariKonteks($context);
        Cpl::query()->create([
            'academic_unit_id' => $unitId,
            'kode' => $data['kode'],
            'deskripsi' => $data['deskripsi'],
            'domain' => $data['domain'] !== '' ? Str::lower($data['domain']) : null,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $cpl = Cpl::query()->findOrFail($existingId);

        $cpl->update([
            'deskripsi' => $data['deskripsi'],
            'domain' => $data['domain'] !== '' ? Str::lower($data['domain']) : $cpl->domain,
        ]);
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
