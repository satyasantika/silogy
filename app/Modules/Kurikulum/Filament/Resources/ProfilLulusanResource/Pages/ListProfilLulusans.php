<?php

namespace App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages;

use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;

class ListProfilLulusans extends ListRecords
{
    use HasImporMassal;

    protected static string $resource = ProfilLulusanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => ProfilLulusanResource::bisaKelola()),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor profil lulusan massal';
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
                ->label('Kurikulum (prodi)')
                ->options(ProfilLulusanResource::kurikulumProdiOptions())
                ->searchable()
                ->required()
                ->default(fn (): ?string => KurikulumTerpilih::current()?->academicUnit?->isProdi()
                    ? KurikulumTerpilih::currentId()
                    : null),
        ];
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'kode', 'label' => 'kode', 'wajib' => true],
            ['key' => 'nama', 'label' => 'nama', 'wajib' => false],
            ['key' => 'deskripsi', 'label' => 'deskripsi', 'wajib' => true],
            ['key' => 'urutan', 'label' => 'urutan', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Seluruh baris diimpor ke kurikulum yang dipilih di atas.';
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if (blank($context['import_kurikulum_id'] ?? null)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih kurikulum terlebih dahulu.'];
        }

        if ($data['urutan'] !== '' && ! ctype_digit($data['urutan'])) {
            return ['status' => 'invalid', 'keterangan' => 'Urutan harus angka.'];
        }

        $existing = ProfilLulusan::query()
            ->where('kurikulum_id', $context['import_kurikulum_id'])
            ->where('kode', $data['kode'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kode profil sudah ada pada kurikulum ini.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['kode']),
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['kode'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        ProfilLulusan::query()->create([
            'kurikulum_id' => $context['import_kurikulum_id'],
            'kode' => $data['kode'],
            'nama' => $data['nama'] ?: null,
            'deskripsi' => $data['deskripsi'],
            'urutan' => $data['urutan'] !== '' ? (int) $data['urutan'] : null,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $profil = ProfilLulusan::query()->findOrFail($existingId);

        $profil->update([
            'nama' => $data['nama'] ?: $profil->nama,
            'deskripsi' => $data['deskripsi'],
            'urutan' => $data['urutan'] !== '' ? (int) $data['urutan'] : $profil->urutan,
        ]);
    }
}
