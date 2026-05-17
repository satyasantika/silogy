<?php

namespace App\Modules\MK\Filament\Resources;

use App\Modules\Kurikulum\Filament\Support\Concerns\HasTimKurikulumUnitScope;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\EditMkUnit;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\ListMkUnits;
use App\Modules\MK\Models\MkUnit;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class MkUnitResource extends Resource
{
    use HasTimKurikulumUnitScope;

    protected static ?string $model = MkUnit::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?string $modelLabel = 'penawaran MK';

    protected static ?string $pluralModelLabel = 'penawaran MK';

    protected static ?string $slug = 'mk-units';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return static::scopeEloquentByTimKurikulumUnits(
            parent::getEloquentQuery()->with(['mk', 'academicUnit']),
        );
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Penawaran MK')
                    ->schema([
                        Select::make('mk_id')
                            ->label('Mata kuliah')
                            ->relationship('mk', 'nama')
                            ->searchable()
                            ->required(),
                        Select::make('academic_unit_id')
                            ->label('Unit penawaran')
                            ->relationship('academicUnit', 'nama')
                            ->searchable()
                            ->required(),
                        TextInput::make('kode')->label('Kode')->required()->maxLength(20),
                        TextInput::make('semester_ke')->label('Semester ke-')->numeric(),
                        Toggle::make('is_active')->label('Aktif')->default(true),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('mk.nama')->label('Mata kuliah'),
                TextColumn::make('academicUnit.nama')->label('Unit'),
                TextColumn::make('kode')->label('Kode'),
                TextColumn::make('semester_ke')->label('Semester ke-'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMkUnits::route('/'),
            'edit' => EditMkUnit::route('/{record}/edit'),
        ];
    }
}
