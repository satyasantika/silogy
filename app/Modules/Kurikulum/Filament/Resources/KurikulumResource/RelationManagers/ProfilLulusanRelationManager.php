<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers;

use App\Modules\Kurikulum\Models\Kurikulum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProfilLulusanRelationManager extends RelationManager
{
    protected static string $relationship = 'profilLulusan';

    protected static ?string $title = 'Profil Lulusan';

    protected static ?string $modelLabel = 'profil lulusan';

    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        /** @var Kurikulum $ownerRecord */
        $ownerRecord->loadMissing('academicUnit');

        return $ownerRecord->academicUnit->isProdi();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('kode')
                    ->label('Kode')
                    ->required()
                    ->maxLength(10),

                TextInput::make('nama')
                    ->label('Nama')
                    ->maxLength(150),

                Textarea::make('deskripsi')
                    ->label('Deskripsi')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),

                TextInput::make('urutan')
                    ->label('Urutan')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(255),

                Repeater::make('indikators')
                    ->label('Indikator')
                    ->relationship()
                    ->schema([
                        Textarea::make('nama')
                            ->label('Nama indikator')
                            ->rows(2),
                        Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->rows(2),
                    ])
                    ->defaultItems(1)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable(),
                TextColumn::make('indikators_count')
                    ->label('Indikator')
                    ->counts('indikators'),
                TextColumn::make('urutan')->label('Urutan'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }
}
