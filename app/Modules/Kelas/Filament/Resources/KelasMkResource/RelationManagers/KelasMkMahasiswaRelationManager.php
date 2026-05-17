<?php

namespace App\Modules\Kelas\Filament\Resources\KelasMkResource\RelationManagers;

use App\Modules\Kelas\Models\KelasMk;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class KelasMkMahasiswaRelationManager extends RelationManager
{
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
}
