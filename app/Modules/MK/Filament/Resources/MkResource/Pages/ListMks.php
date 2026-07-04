<?php

namespace App\Modules\MK\Filament\Resources\MkResource\Pages;

use App\Models\User;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkResource;
use App\Modules\MK\Models\Mk;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

class ListMks extends ListRecords
{
    use HasImporMassal;

    protected static string $resource = MkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => MkResource::canCreate()),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor mata kuliah massal';
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
            ['key' => 'nama', 'label' => 'nama', 'wajib' => true],
            ['key' => 'sks_teori', 'label' => 'sks teori', 'wajib' => true],
            ['key' => 'sks_praktik', 'label' => 'sks praktik', 'wajib' => false],
            ['key' => 'sks_lapangan', 'label' => 'sks lapangan', 'wajib' => false],
            ['key' => 'jenis', 'label' => 'jenis', 'wajib' => true],
            ['key' => 'username_koordinator', 'label' => 'username koordinator', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Jenis: wajib, pilihan, atau praktikum. SKS praktik/lapangan kosong dianggap 0.';
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        $unitId = $this->unitIdDariKonteks($context);

        if (blank($unitId)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih kurikulum pemilik MK terlebih dahulu.'];
        }

        foreach (['sks_teori', 'sks_praktik', 'sks_lapangan'] as $key) {
            if ($data[$key] !== '' && ! ctype_digit($data[$key])) {
                return ['status' => 'invalid', 'keterangan' => 'SKS harus berupa angka.'];
            }
        }

        if (! in_array(Str::lower($data['jenis']), ['wajib', 'pilihan', 'praktikum'], true)) {
            return ['status' => 'invalid', 'keterangan' => 'Jenis harus wajib, pilihan, atau praktikum.'];
        }

        if ($data['username_koordinator'] !== ''
            && ! User::query()->where('username', $data['username_koordinator'])->exists()) {
            return ['status' => 'invalid', 'keterangan' => "Koordinator '{$data['username_koordinator']}' tidak ditemukan."];
        }

        $existing = Mk::query()
            ->where('academic_unit_id', $unitId)
            ->where('nama', $data['nama'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'MK dengan nama ini sudah ada pada unit.',
                'existing_id' => $existing->id,
                'dedup' => mb_strtolower($data['nama']),
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => mb_strtolower($data['nama'])];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $unitId = $this->unitIdDariKonteks($context);
        [$teori, $praktik, $lapangan] = $this->sksDariData($data);

        Mk::query()->create([
            'academic_unit_id' => $unitId,
            'nama' => $data['nama'],
            'sks_teori' => $teori,
            'sks_praktik' => $praktik,
            'sks_lapangan' => $lapangan,
            'sks' => $teori + $praktik + $lapangan,
            'jenis' => Str::lower($data['jenis']),
            'koordinator_mk_id' => $this->koordinatorId($data),
            'state' => 'draft',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        [$teori, $praktik, $lapangan] = $this->sksDariData($data);
        $mk = Mk::query()->findOrFail($existingId);

        $mk->update([
            'sks_teori' => $teori,
            'sks_praktik' => $praktik,
            'sks_lapangan' => $lapangan,
            'sks' => $teori + $praktik + $lapangan,
            'jenis' => Str::lower($data['jenis']),
            'koordinator_mk_id' => $this->koordinatorId($data) ?? $mk->koordinator_mk_id,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @return array{0: int, 1: int, 2: int}
     */
    protected function sksDariData(array $data): array
    {
        return [
            (int) $data['sks_teori'],
            $data['sks_praktik'] !== '' ? (int) $data['sks_praktik'] : 0,
            $data['sks_lapangan'] !== '' ? (int) $data['sks_lapangan'] : 0,
        ];
    }

    /**
     * @param  array<string, string>  $data
     */
    protected function koordinatorId(array $data): ?string
    {
        if ($data['username_koordinator'] === '') {
            return null;
        }

        return User::query()->where('username', $data['username_koordinator'])->value('id');
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
