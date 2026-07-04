<?php

namespace App\Modules\CPL\Filament\Resources\CplResource\RelationManagers;

use App\Modules\CPL\Models\Cpl;
use Filament\Actions\AttachAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BokRelationManager extends RelationManager
{
    protected static string $relationship = 'boks';

    protected static ?string $title = 'Bahan Kajian (BoK)';

    protected static ?string $modelLabel = 'BoK';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode'),
                TextColumn::make('nama')->label('Nama'),
                TextColumn::make('pivot.bobot')->label('Bobot (%)'),
            ])
            ->headerActions([
                AttachAction::make()
                    ->recordSelectOptionsQuery(function (Builder $query): Builder {
                        /** @var Cpl $cpl */
                        $cpl = $this->getOwnerRecord();

                        return $query->where('academic_unit_id', $cpl->academic_unit_id);
                    })
                    ->preloadRecordSelect()
                    ->schema([
                        TextInput::make('bobot')
                            ->label('Bobot (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                    ]),
            ])
            ->recordActions([
                DetachAction::make(),
            ])
            ->toolbarActions([
                DetachBulkAction::make(),
            ]);
    }
}
