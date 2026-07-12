<?php

namespace App\Modules\Kelas\Filament\Resources\KelasMkResource\RelationManagers;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kelas\Policies\KelasMkPolicy;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Support\Filament\Concerns\HasImporMassal;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class KelasMkMahasiswaRelationManager extends RelationManager
{
    use HasImporMassal;

    protected static string $relationship = 'mahasiswas';

    protected static ?string $title = 'Mahasiswa Terdaftar';

    protected static ?string $modelLabel = 'mahasiswa';

    /**
     * Akses ditentukan policy kelas MK (termasuk dosen pengampu kelas ini),
     * bukan policy modul Mahasiswa.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $ownerRecord instanceof KelasMk
            && app(KelasMkPolicy::class)->view($user, $ownerRecord);
    }

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
                    ->visible(fn (): bool => $this->bolehKelolaMahasiswa()),
                AttachAction::make()
                    ->label('Daftarkan mahasiswa')
                    ->visible(fn (): bool => $this->bolehKelolaMahasiswa())
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordTitle(fn (Mahasiswa $record): string => trim("{$record->nama} ({$record->nim})"))
                    ->recordSelectSearchColumns(['nama', 'nim'])
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
                    ->label('Batalkan pendaftaran')
                    ->icon(Heroicon::OutlinedUserMinus)
                    ->iconButton()
                    ->tooltip('Batalkan pendaftaran')
                    ->visible(fn (Mahasiswa $record): bool => $this->bolehKelolaMahasiswa() && ! $this->sudahDinilai($record)),
            ])
            ->toolbarActions([
                DetachBulkAction::make()
                    ->label('Batalkan pendaftaran terpilih'),
            ]);
    }

    /** Pendaftaran mahasiswa: Dosen Pengampu kelas ini atau Admin prodi. */
    protected function bolehKelolaMahasiswa(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        /** @var KelasMk $kelas */
        $kelas = $this->getOwnerRecord();

        return app(KelasMkPolicy::class)->kelolaMahasiswa($user, $kelas);
    }

    /** Mahasiswa yang sudah punya nilai tidak boleh dibatalkan pendaftarannya. */
    protected function sudahDinilai(Mahasiswa $record): bool
    {
        $pivot = $record->pivot;

        if (! $pivot instanceof KelasMkMahasiswa) {
            return false;
        }

        return filled($pivot->nilai_angka) || filled($pivot->nilai_huruf);
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

    /**
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [
            '227000001',
            '227000002',
        ];
    }

    protected function importSupportsOverwrite(): bool
    {
        return false;
    }

    protected function resolveImportRow(array $data, array $context): array
    {
        /** @var KelasMk $kelas */
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
        /** @var KelasMk $kelas */
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
