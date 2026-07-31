<?php

namespace App\Modules\MK\Filament\Resources\MkResource\RelationManagers;

use App\Modules\MK\Filament\Resources\MkResource;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class MkUnitRelationManager extends RelationManager
{
    protected static string $relationship = 'mkUnits';

    protected static ?string $title = 'Penawaran MK per Unit';

    protected static ?string $modelLabel = 'penawaran MK';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('academic_unit_id')
                    ->label('Unit penawaran')
                    ->options(function (): array {
                        /** @var Mk $mk */
                        $mk = $this->getOwnerRecord();

                        return MkResource::descendantUnitOptionsForMk($mk);
                    })
                    ->searchable()
                    ->required(),

                TextInput::make('kode')
                    ->label('Kode MK di unit')
                    ->required()
                    ->maxLength(20)
                    ->rule(fn (Get $get, ?MkUnit $record): array => [
                        (new Unique('mk_units', 'kode'))
                            ->where('academic_unit_id', $get('academic_unit_id'))
                            ->ignore($record?->id),
                    ]),

                TextInput::make('semester_ke')
                    ->label('Semester ke-')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(14),

                Toggle::make('is_active')
                    ->label('Aktif')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('academicUnit.nama_lengkap')->label('Unit'),
                TextColumn::make('kode')->label('Kode'),
                TextColumn::make('semester_ke')->label('Semester ke-'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
