<?php

namespace App\Modules\CPL\Filament\Resources\CplResource\RelationManagers;

use App\Modules\CPL\Models\Cpl;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProfilLulusanRelationManager extends RelationManager
{
    protected static string $relationship = 'profilLulusan';

    protected static ?string $title = 'Profil Lulusan';

    protected static ?string $modelLabel = 'profil lulusan';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        /** @var Cpl $ownerRecord */
        $ownerRecord->loadMissing('academicUnit');

        return $ownerRecord->academicUnit->isProdi();
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode'),
                TextColumn::make('nama')->label('Nama'),
                TextColumn::make('kurikulum.nama')->label('Kurikulum'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelectOptionsQuery(function (Builder $query): Builder {
                        /** @var Cpl $cpl */
                        $cpl = $this->getOwnerRecord();

                        return $query->whereHas(
                            'kurikulum',
                            fn (Builder $kurikulumQuery): Builder => $kurikulumQuery->where(
                                'academic_unit_id',
                                $cpl->academic_unit_id,
                            ),
                        );
                    })
                    ->preloadRecordSelect(),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ]);
    }
}
