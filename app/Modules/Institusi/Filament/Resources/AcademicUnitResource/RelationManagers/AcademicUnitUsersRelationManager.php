<?php

namespace App\Modules\Institusi\Filament\Resources\AcademicUnitResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AcademicUnitUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Pengguna Unit';

    protected static ?string $modelLabel = 'penugasan pengguna';

    protected static ?string $pluralModelLabel = 'penugasan pengguna';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Pengguna')
                    ->relationship('user', 'full_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->full_name} ({$record->username})")
                    ->searchable(['full_name', 'username', 'email'])
                    ->preload()
                    ->required()
                    ->disabledOn('edit'),

                Toggle::make('status_pimpinan')
                    ->label('Pimpinan unit')
                    ->inline(false),

                Toggle::make('status_tim_kurikulum')
                    ->label('Tim kurikulum')
                    ->inline(false),

                TextInput::make('jabatan')
                    ->label('Jabatan')
                    ->maxLength(100),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('jabatan')
            ->columns([
                TextColumn::make('user.full_name')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('user.username')
                    ->label('Username')
                    ->searchable(),
                TextColumn::make('user.email')
                    ->label('Email')
                    ->toggleable(),
                IconColumn::make('status_pimpinan')
                    ->label('Pimpinan')
                    ->boolean(),
                IconColumn::make('status_tim_kurikulum')
                    ->label('Tim kurikulum')
                    ->boolean(),
                TextColumn::make('jabatan')
                    ->label('Jabatan')
                    ->placeholder('—'),
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
