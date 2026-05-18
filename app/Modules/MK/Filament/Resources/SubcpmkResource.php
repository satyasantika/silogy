<?php

namespace App\Modules\MK\Filament\Resources;

use App\Models\User;
use App\Modules\MK\Filament\Resources\CpmkResource\RelationManagers\MkCpmkRelationManager;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\CreateSubcpmk;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\EditSubcpmk;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\ListSubcpmks;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Policies\SubcpmkPolicy;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SubcpmkResource extends Resource
{
    use HasKoordinatorMkScope;

    protected static ?string $model = Subcpmk::class;

    protected static ?string $policy = SubcpmkPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static string|\UnitEnum|null $navigationGroup = 'Mata Kuliah';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'Sub-CPMK';

    protected static ?string $pluralModelLabel = 'Sub-CPMK';

    protected static ?string $slug = 'subcpmk';

    /**
     * @return array<string, string>
     */
    public static function bloomKognitifOptions(): array
    {
        return [
            'C1' => 'C1',
            'C2' => 'C2',
            'C3' => 'C3',
            'C4' => 'C4',
            'C5' => 'C5',
            'C6' => 'C6',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function bloomAfektifOptions(): array
    {
        return [
            'A1' => 'A1',
            'A2' => 'A2',
            'A3' => 'A3',
            'A4' => 'A4',
            'A5' => 'A5',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function bloomPsikomotorikOptions(): array
    {
        return [
            'P1' => 'P1',
            'P2' => 'P2',
            'P3' => 'P3',
            'P4' => 'P4',
            'P5' => 'P5',
            'P6' => 'P6',
            'P7' => 'P7',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function mkCpmkOptions(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $mkIds = static::scopedKoordinatorMkIds($user);

        if ($mkIds->isEmpty()) {
            return [];
        }

        return MkCpmk::query()
            ->whereHas('cpmk', fn (Builder $query): Builder => $query->whereIn('mk_id', $mkIds))
            ->with(['cpmk', 'cplMk.cplBok.cpl'])
            ->get()
            ->mapWithKeys(fn (MkCpmk $mkCpmk): array => [
                $mkCpmk->id => MkCpmkRelationManager::formatMkCpmkLabel($mkCpmk),
            ])
            ->all();
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return $user instanceof User && app(SubcpmkPolicy::class)->viewAny($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['mkCpmk.cpmk', 'semester']);

        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return $query;
        }

        $mkIds = static::scopedKoordinatorMkIds($user);

        if ($mkIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'mkCpmk.cpmk',
            fn (Builder $cpmkQuery): Builder => $cpmkQuery->whereIn('mk_id', $mkIds),
        );
    }

    public static function form(Schema $schema): Schema
    {
        $mkCpmkOptions = static::mkCpmkOptions();

        return $schema
            ->components([
                Section::make('Sub-CPMK')
                    ->schema([
                        Select::make('mk_cpmk_id')
                            ->label('CPMK (via CPL–MK)')
                            ->options($mkCpmkOptions)
                            ->searchable()
                            ->required()
                            ->default(count($mkCpmkOptions) === 1 ? array_key_first($mkCpmkOptions) : null)
                            ->disabled(count($mkCpmkOptions) === 1)
                            ->dehydrated(),

                        Select::make('semester_id')
                            ->label('Semester')
                            ->relationship(
                                'semester',
                                'nama',
                                fn (Builder $query): Builder => $query->orderBy('kode'),
                            )
                            ->searchable()
                            ->preload(),

                        TextInput::make('kode')
                            ->label('Kode')
                            ->required()
                            ->maxLength(15),

                        Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('indikator')
                            ->label('Indikator')
                            ->rows(2)
                            ->columnSpanFull(),

                        Textarea::make('evaluasi')
                            ->label('Evaluasi')
                            ->rows(2)
                            ->columnSpanFull(),

                        TextInput::make('bobot')
                            ->label('Bobot (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),

                        Select::make('bloom_kognitif')
                            ->label('Bloom kognitif')
                            ->options(static::bloomKognitifOptions()),

                        Select::make('bloom_afektif')
                            ->label('Bloom afektif')
                            ->options(static::bloomAfektifOptions()),

                        Select::make('bloom_psikomotorik')
                            ->label('Bloom psikomotorik')
                            ->options(static::bloomPsikomotorikOptions()),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')
                    ->label('Kode')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('deskripsi')
                    ->label('Deskripsi')
                    ->limit(50),

                TextColumn::make('cpmk.kode')
                    ->label('CPMK')
                    ->getStateUsing(fn (Subcpmk $record): string => $record->cpmk?->kode ?? '—'),

                TextColumn::make('bobot')
                    ->label('Bobot')
                    ->suffix('%')
                    ->placeholder('—'),

                TextColumn::make('semester.kode')
                    ->label('Semester')
                    ->placeholder('—'),
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
            'index' => ListSubcpmks::route('/'),
            'create' => CreateSubcpmk::route('/create'),
            'edit' => EditSubcpmk::route('/{record}/edit'),
        ];
    }
}
