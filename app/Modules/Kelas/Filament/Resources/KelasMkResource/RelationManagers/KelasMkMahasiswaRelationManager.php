<?php

namespace App\Modules\Kelas\Filament\Resources\KelasMkResource\RelationManagers;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KelasMkMahasiswaRelationManager extends RelationManager
{
    use HasImporMassal;

    protected static string $relationship = 'mahasiswas';

    protected static ?string $title = 'Mahasiswa Terdaftar';

    protected static ?string $modelLabel = 'mahasiswa';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nim')
                    ->label('NIM')
                    ->searchable(),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable(),

                TextColumn::make('pivot.nilai_angka')
                    ->label('Nilai angka')
                    ->placeholder('—'),

                TextColumn::make('pivot.nilai_huruf')
                    ->label('Nilai huruf')
                    ->placeholder('—'),
            ])
            ->headerActions([
                $this->makeImporMassalAction()
                    ->label('Impor NIM massal')
                    ->visible(fn (): bool => $this->canAttach()),
                AttachAction::make()
                    ->label('Daftarkan mahasiswa')
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function (Builder $query): Builder {
                        /** @var KelasMk $kelasMk */
                        $kelasMk = $this->getOwnerRecord();
                        $kelasMk->loadMissing('mkUnit');

                        $prodiId = $kelasMk->mkUnit?->academic_unit_id;

                        if ($prodiId === null) {
                            return $query->whereRaw('1 = 0');
                        }

                        return $query
                            ->where('academic_unit_id', $prodiId)
                            ->orderBy('nama');
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Batalkan pendaftaran'),
            ])
            ->toolbarActions([
                DetachBulkAction::make()
                    ->label('Batalkan pendaftaran terpilih'),
            ]);
    }

    protected function importModalHeading(): string
    {
        return 'Impor mahasiswa massal ke kelas ini';
    }

    protected function importColumns(): array
    {
        return [
            ['key' => 'nim', 'label' => 'nim', 'wajib' => true],
        ];
    }

    protected function importHelperNote(): string
    {
        return 'Cukup tempel daftar NIM (satu per baris). Mahasiswa harus terdaftar '
            .'pada program studi pemilik penawaran kelas ini.';
    }

    protected function importSupportsOverwrite(): bool
    {
        return false;
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        $kelas = $this->getOwnerRecord();
        $kelas->loadMissing('mkUnit');

        $mahasiswa = Mahasiswa::query()
            ->where('nim', $data['nim'])
            ->where('academic_unit_id', $kelas->mkUnit?->academic_unit_id)
            ->first();

        if (! $mahasiswa) {
            return ['status' => 'invalid', 'keterangan' => 'NIM tidak ditemukan pada prodi kelas ini.'];
        }

        $terdaftar = KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelas->getKey())
            ->where('mahasiswa_id', $mahasiswa->id)
            ->exists();

        if ($terdaftar) {
            return [
                'status' => 'duplikat',
                'keterangan' => 'Sudah terdaftar di kelas ini.',
                'existing_id' => $mahasiswa->id,
                'dedup' => $data['nim'],
            ];
        }

        return ['status' => 'baru', 'keterangan' => $mahasiswa->nama ?? '', 'dedup' => $data['nim']];
    }

    protected function createImportRow(array $data, array $context): void
    {
        $kelas = $this->getOwnerRecord();
        $kelas->loadMissing('mkUnit');

        $mahasiswa = Mahasiswa::query()
            ->where('nim', $data['nim'])
            ->where('academic_unit_id', $kelas->mkUnit?->academic_unit_id)
            ->firstOrFail();

        KelasMkMahasiswa::query()->firstOrCreate([
            'kelas_mk_id' => $kelas->getKey(),
            'mahasiswa_id' => $mahasiswa->id,
        ]);
    }
}
