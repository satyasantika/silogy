<?php

namespace App\Modules\BoK\Filament\Resources;

use App\Modules\BoK\Filament\Resources\BokResource\Pages\CreateBok;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\EditBok;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\ListBoks;
use App\Modules\BoK\Models\Bok;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumTerpilihFilter;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasTimKurikulumUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Support\Filament\DelegasiMenu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BokResource extends Resource
{
    use HasKurikulumTerpilihFilter;
    use HasTimKurikulumUnitScope;

    protected static ?string $model = Bok::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'BoK';

    protected static ?string $pluralModelLabel = 'BoK';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $slug = 'boks';

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

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

                        RichEditor::make('deskripsi')
                            ->label('Deskripsi')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyKurikulumTerpilihCardTable(
            $table
                ->recordActions([
                    EditAction::make(),
                ])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            [
                TextColumn::make('kode')
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('deskripsi')
                    ->label('Deskripsi')
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? Str::limit(trim(strip_tags($state)), 100)
                        : '—')
                    ->size('sm')
                    ->color('gray')
                    ->placeholder('—'),
            ],
            fn (Builder $query, Kurikulum $kurikulum): Builder => $query
                ->where('academic_unit_id', $kurikulum->academic_unit_id),
        );
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
