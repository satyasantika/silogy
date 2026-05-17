<?php

namespace App\Modules\BoK\Filament\Resources;

use App\Modules\BoK\Filament\Resources\BokResource\Pages\CreateBok;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\EditBok;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\ListBoks;
use App\Modules\BoK\Models\Bok;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasTimKurikulumUnitScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BokResource extends Resource
{
    use HasTimKurikulumUnitScope;

    protected static ?string $model = Bok::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'BoK';

    protected static ?string $pluralModelLabel = 'BoK';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $slug = 'boks';

    public static function getEloquentQuery(): Builder
    {
        return static::scopeEloquentByTimKurikulumUnits(
            parent::getEloquentQuery()->with('academicUnit'),
        );
    }

    public static function form(Schema $schema): Schema
    {
        $unitIds = static::scopedTimKurikulumUnitIds();

        return $schema
            ->components([
                Section::make('Data BoK')
                    ->schema([
                        Select::make('academic_unit_id')
                            ->label('Unit akademik')
                            ->options(static::timKurikulumUnitOptions())
                            ->searchable()
                            ->required()
                            ->live()
                            ->default($unitIds->count() === 1 ? $unitIds->first() : null)
                            ->disabled($unitIds->count() === 1)
                            ->dehydrated(),

                        TextInput::make('kode')
                            ->label('Kode')
                            ->required()
                            ->maxLength(15)
                            ->rule(fn (Get $get, ?Bok $record): array => [
                                static::uniqueKodePerUnitRule(
                                    'bok',
                                    $record?->id,
                                    $get('academic_unit_id'),
                                ),
                            ]),

                        TextInput::make('nama')
                            ->label('Nama')
                            ->required()
                            ->maxLength(150),

                        Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable()->sortable(),
                TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                TextColumn::make('academicUnit.nama')->label('Unit')->sortable(),
            ])
            ->filters([
                SelectFilter::make('academic_unit_id')
                    ->label('Unit')
                    ->relationship('academicUnit', 'nama', fn (Builder $query) => $query->whereIn(
                        'id',
                        static::scopedTimKurikulumUnitIds(),
                    )),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBoks::route('/'),
            'create' => CreateBok::route('/create'),
            'edit' => EditBok::route('/{record}/edit'),
        ];
    }
}
