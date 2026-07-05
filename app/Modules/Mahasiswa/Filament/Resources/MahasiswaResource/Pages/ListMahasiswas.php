<?php

namespace App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource\Pages;

use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

class ListMahasiswas extends ListRecords
{
    use HasImporMassal;

    protected static string $resource = MahasiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->visible(fn (): bool => MahasiswaResource::canCreate()),
            CreateAction::make(),
        ];
    }

    protected function importModalHeading(): string
    {
        return 'Impor mahasiswa massal';
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
        $prodiIds = MahasiswaResource::scopedStudyProgramIds();

        return [
            Select::make('import_unit_id')
                ->label('Program studi tujuan')
                ->options(fn (): array => AcademicUnit::query()
                    ->whereIn('id', $prodiIds)
                    ->orderBy('nama')
                    ->get()
                    ->mapWithKeys(fn (AcademicUnit $unit): array => [
                        $unit->id => AcademicUnitResource::formatUnitOptionLabel($unit),
                    ])
                    ->all())
                ->searchable()
                ->required()
                ->default($prodiIds->count() === 1 ? $prodiIds->first() : null),
        ];
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'nim', 'label' => 'nim', 'wajib' => true],
            ['key' => 'nama', 'label' => 'nama', 'wajib' => true],
            ['key' => 'angkatan', 'label' => 'angkatan', 'wajib' => false],
            ['key' => 'jenis_kelamin', 'label' => 'jenis kelamin (L/P)', 'wajib' => false],
            ['key' => 'email', 'label' => 'email', 'wajib' => false],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Seluruh baris diimpor ke program studi yang dipilih di atas.';
    }

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            '227000001|Mahasiswa Satu|2022|L|mahasiswa1@silogy.test',
            '227000002|Mahasiswa Dua|2022|P|',
        ];
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        if (blank($context['import_unit_id'] ?? null)) {
            return ['status' => 'invalid', 'keterangan' => 'Pilih program studi tujuan terlebih dahulu.'];
        }

        if ($data['jenis_kelamin'] !== '' && ! in_array(Str::upper($data['jenis_kelamin']), ['L', 'P'], true)) {
            return ['status' => 'invalid', 'keterangan' => 'Jenis kelamin harus L atau P.'];
        }

        if ($data['email'] !== '' && ! filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['status' => 'invalid', 'keterangan' => 'Email tidak valid.'];
        }

        $existing = Mahasiswa::query()->where('nim', $data['nim'])->first();

        if ($existing) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'NIM sudah terdaftar.',
                'existing_id' => $existing->id,
                'dedup' => $data['nim'],
            ];
        }

        return ['status' => 'baru', 'keterangan' => '', 'dedup' => $data['nim']];
    }

    protected function createImportRow(array $data, array $context): void
    {
        Mahasiswa::query()->create([
            'nim' => $data['nim'],
            'nama' => $data['nama'],
            'angkatan' => $data['angkatan'] ?: null,
            'jenis_kelamin' => $data['jenis_kelamin'] !== '' ? Str::upper($data['jenis_kelamin']) : null,
            'email' => $data['email'] ?: null,
            'academic_unit_id' => $context['import_unit_id'],
            'status' => 'aktif',
        ]);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        $mahasiswa = Mahasiswa::query()->findOrFail($existingId);

        $mahasiswa->update([
            'nama' => $data['nama'],
            'angkatan' => $data['angkatan'] ?: $mahasiswa->angkatan,
            'jenis_kelamin' => $data['jenis_kelamin'] !== '' ? Str::upper($data['jenis_kelamin']) : $mahasiswa->jenis_kelamin,
            'email' => $data['email'] ?: $mahasiswa->email,
        ]);
    }
}
