<?php

namespace App\Modules\CPL\Filament\Resources;

use App\Modules\CPL\Filament\Resources\CplResource\Pages\CreateCpl;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\EditCpl;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\CPL\Filament\Resources\CplResource\RelationManagers\BokRelationManager;
use App\Modules\CPL\Filament\Resources\CplResource\RelationManagers\ProfilLulusanRelationManager;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumTerpilihFilter;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasTimKurikulumUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;
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
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CplResource extends Resource
{
    use HasKurikulumTerpilihFilter;
    use HasTimKurikulumUnitScope;

    protected static ?string $model = Cpl::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'CPL';

    protected static ?string $pluralModelLabel = 'CPL';

    protected static ?string $recordTitleAttribute = 'kode';

    protected static ?string $slug = 'cpls';

    /**
     * @return array<string, string>
     */
    public static function domainOptions(): array
    {
        return [
            'kognitif' => 'Kognitif',
            'afektif' => 'Afektif',
            'psikomotorik' => 'Psikomotorik',
            'gabungan' => 'Gabungan',
        ];
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
                Section::make('Data CPL')
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
                            ->rule(fn (Get $get, ?Cpl $record): array => [
                                static::uniqueKodePerUnitRule(
                                    'cpl',
                                    $record?->id,
                                    $get('academic_unit_id'),
                                ),
                            ]),

                        Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),

                        Select::make('domain')
                            ->label('Domain')
                            ->options(static::domainOptions()),
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
                TextColumn::make('deskripsi')->label('Deskripsi')->limit(50),
                TextColumn::make('domain')
                    ->label('Domain')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state ? (static::domainOptions()[$state] ?? $state) : '—'),
                TextColumn::make('academicUnit.nama')->label('Unit')->sortable(),
            ])
            ->filters([
                static::kurikulumTerpilihFilter(fn (Builder $query, Kurikulum $kurikulum): Builder => $query->where('academic_unit_id', $kurikulum->academic_unit_id)),
                SelectFilter::make('academic_unit_id')
                    ->label('Unit')
                    ->relationship('academicUnit', 'nama', fn (Builder $query) => $query->whereIn(
                        'id',
                        static::scopedTimKurikulumUnitIds(),
                    )),
                SelectFilter::make('domain')
                    ->label('Domain')
                    ->options(static::domainOptions()),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProfilLulusanRelationManager::class,
            BokRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCpls::route('/'),
            'create' => CreateCpl::route('/create'),
            'edit' => EditCpl::route('/{record}/edit'),
        ];
    }
}
