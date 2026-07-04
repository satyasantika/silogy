<?php

namespace App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\MkUnit;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;

class ListKelasMks extends ListRecords
{
    use HasImporMassal;

    protected static string $resource = KelasMkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => KelasMkResource::canCreate()),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor kelas MK massal';
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return ['import_semester_id'];
    }

    /**
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return [
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
            ['key' => 'kode_penawaran', 'label' => 'kode penawaran MK', 'wajib' => true],
            ['key' => 'kode_kelas', 'label' => 'kode kelas', 'wajib' => true],
            ['key' => 'username_dosen', 'label' => 'username dosen pengampu', 'wajib' => false],
            ['key' => 'username_koordinator', 'label' => 'username koordinator', 'wajib' => false],
            ['key' => 'kapasitas', 'label' => 'kapasitas', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Kode penawaran MK = kode pada menu Penawaran MK di unit Anda. '
            .'Koordinator kosong akan diisi otomatis dari koordinator MK.';
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if (blank($context['import_semester_id'] ?? null)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih semester terlebih dahulu.'];
        }

        $mkUnit = $this->cariMkUnit($data['kode_penawaran']);

        if (! $mkUnit) {
            return ['status' => 'invalid', 'keterangan' => "Penawaran MK '{$data['kode_penawaran']}' tidak ditemukan pada unit yang dapat Anda kelola."];
        }

        foreach (['username_dosen', 'username_koordinator'] as $key) {
            if ($data[$key] !== '' && ! User::query()->where('username', $data[$key])->exists()) {
                return ['status' => 'invalid', 'keterangan' => "Pengguna '{$data[$key]}' tidak ditemukan."];
            }
        }

        if ($data['kapasitas'] !== '' && ! ctype_digit($data['kapasitas'])) {
            return ['status' => 'invalid', 'keterangan' => 'Kapasitas harus berupa angka.'];
        }

        $dedup = mb_strtolower($data['kode_penawaran'].'/'.$data['kode_kelas']);

        $existing = KelasMk::query()
            ->where('mk_unit_id', $mkUnit->id)
            ->where('semester_id', $context['import_semester_id'])
            ->where('kode_kelas', $data['kode_kelas'])
            ->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Kelas dengan kode ini sudah ada pada penawaran dan semester tersebut.',
                'existing_id' => $existing->id,
                'dedup' => $dedup,
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => $dedup];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $mkUnit = $this->cariMkUnit($data['kode_penawaran']);

        KelasMk::query()->create([
            'mk_unit_id' => $mkUnit?->id,
            'semester_id' => $context['import_semester_id'],
            'kode_kelas' => $data['kode_kelas'],
            'dosen_pengampu_id' => $this->userId($data['username_dosen']),
            'koordinator_mk_id' => $this->userId($data['username_koordinator'])
                ?? $mkUnit?->mk?->koordinator_mk_id,
            'kapasitas' => $data['kapasitas'] !== '' ? (int) $data['kapasitas'] : null,
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $kelas = KelasMk::query()->findOrFail($existingId);

        $kelas->update([
            'dosen_pengampu_id' => $this->userId($data['username_dosen']) ?? $kelas->dosen_pengampu_id,
            'koordinator_mk_id' => $this->userId($data['username_koordinator']) ?? $kelas->koordinator_mk_id,
            'kapasitas' => $data['kapasitas'] !== '' ? (int) $data['kapasitas'] : $kelas->kapasitas,
        ]);
    }

    protected function cariMkUnit(string $kodePenawaran): ?MkUnit
    {
        $unitIds = KelasMkResource::scopedAccessibleUnitIds();

        return MkUnit::query()
            ->with('mk')
            ->whereIn('academic_unit_id', $unitIds)
            ->where('kode', $kodePenawaran)
            ->first();
    }

    protected function userId(string $username): ?string
    {
        if ($username === '') {
            return null;
        }

        return User::query()->where('username', $username)->value('id');
    }
}
