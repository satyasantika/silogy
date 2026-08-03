<?php

namespace App\Modules\CPL\Filament\Resources;

use App\Modules\CPL\Filament\Resources\CplResource\Pages\CreateCpl;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\EditCpl;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplKodeOverride;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumTerpilihFilter;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasTimKurikulumUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
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
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CplResource extends Resource
{
    use HasKurikulumTerpilihFilter;
    use HasTimKurikulumUnitScope;

    protected static ?string $model = Cpl::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Kurikulum');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('cpl', 3);
    }

    protected static ?string $modelLabel = 'CPL';

    protected static ?string $pluralModelLabel = 'CPL';

    protected static ?string $recordTitleAttribute = 'kode';

    protected static ?string $slug = 'cpls';

    protected static function kurikulumBannerCatatan(): ?string
    {
        return 'CPL yang tampil, ditambahkan, maupun diimpor mengikuti kurikulum ini.';
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        return static::canAccess();
    }

    /**
     * @return array<string, string>
     */
    public static function domainOptions(): array
    {
        return [
            'kognitif' => 'Kognitif',
            'afektif' => 'Afektif',
            'psikomotorik' => 'Psikomotorik',
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('academicUnit');

        if (auth()->user()?->hasRole('Super Admin')) {
            return $query;
        }

        $unitIds = static::scopedTimKurikulumUnitIds();
        $adaptasi = $unitIds->flatMap(fn (string $unitId) => CplBokAdaptasiScope::adaptedCplIdsAcrossUnit($unitId));

        if ($adaptasi->isEmpty()) {
            return static::scopeEloquentByTimKurikulumUnits($query);
        }

        // Dikelompokkan dalam satu where() supaya filter kurikulum_id yang
        // ditambahkan belakangan (lihat table()) benar-benar AND terhadap
        // keseluruhan kondisi ini — sebelumnya orWhereIn() top-level di sini
        // membuat AND lanjutan hanya mengikat ke klausa OR terakhir (bug
        // precedence SQL: AND mengikat lebih erat dari OR), sehingga
        // academic_unit_id IN (...) meloloskan CPL dari kurikulum manapun
        // pada unit yang sama.
        return $query->where(
            fn (Builder $scoped): Builder => $scoped
                ->whereIn('academic_unit_id', $unitIds)
                ->orWhereIn('id', $adaptasi),
        );
    }

    public static function form(Schema $schema): Schema
    {
        $unitIds = static::scopedTimKurikulumUnitIds();
        // "Asing" = record bukan milik unit manapun yang saya kelola sebagai
        // Tim Kurikulum — independen dari Kurikulum yang sedang terpilih di
        // sesi, supaya konsisten dengan getEloquentQuery()/CplPolicy::manage().
        $isAsing = fn (?Cpl $record): bool => $record !== null && ! $unitIds->contains($record->academic_unit_id);

        // Unit MILIK SAYA yang benar-benar mengadaptasi CPL asing ini — di situlah
        // override kode-nya berlaku (satu CPL asing bisa diadaptasi lebih dari
        // satu unit milik saya sekaligus, masing-masing dengan alias sendiri).
        $overrideUnitId = fn (?Cpl $record): ?string => $record
            ? $unitIds->first(fn (string $unitId): bool => CplBokAdaptasiScope::adaptedCplIdsAcrossUnit($unitId)->contains($record->id))
            : null;

        return $schema
            ->components([
                Section::make('Data CPL')
                    ->schema([
                        Select::make('academic_unit_id')
                            ->label('Unit akademik')
                            ->options(function (?Cpl $record) use ($isAsing): array {
                                $options = static::timKurikulumUnitOptions();

                                if ($isAsing($record)) {
                                    $record->loadMissing('academicUnit');
                                    $options[$record->academic_unit_id] = $record->academicUnit?->nama_lengkap ?? '—';
                                }

                                return $options;
                            })
                            ->searchable()
                            ->required(fn (?Cpl $record): bool => ! $isAsing($record))
                            ->live()
                            ->default($unitIds->count() === 1 ? $unitIds->first() : null)
                            ->disabled(fn (?Cpl $record): bool => $isAsing($record) || $unitIds->count() === 1)
                            ->dehydrated(fn (?Cpl $record): bool => ! $isAsing($record)),

                        TextInput::make('kode')
                            ->label('Kode')
                            ->required(fn (?Cpl $record): bool => ! $isAsing($record))
                            ->maxLength(15)
                            ->disabled($isAsing)
                            ->dehydrated(fn (?Cpl $record): bool => ! $isAsing($record))
                            ->rule(fn (Get $get, ?Cpl $record): array => [
                                static::uniqueKodePerKurikulumRule(
                                    'cpl',
                                    $record?->id,
                                    $record?->kurikulum_id ?? KurikulumTerpilih::currentId() ?? $get('kurikulum_id'),
                                ),
                            ]),

                        TextInput::make('kode_override')
                            ->label('Kode (alias di unit ini)')
                            ->helperText('CPL ini milik unit lain — hanya kode tampilannya yang bisa diseragamkan untuk unit Anda.')
                            ->visible($isAsing)
                            ->required($isAsing)
                            ->maxLength(15)
                            ->default(function (?Cpl $record) use ($overrideUnitId): ?string {
                                if (! $record) {
                                    return null;
                                }

                                $unitId = $overrideUnitId($record);

                                if ($unitId === null) {
                                    return $record->kode;
                                }

                                return CplKodeOverride::query()
                                    ->where('academic_unit_id', $unitId)
                                    ->where('cpl_id', $record->id)
                                    ->value('kode') ?? $record->kode;
                            })
                            ->rule(fn (?Cpl $record): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($record, $overrideUnitId): void {
                                $unitId = $overrideUnitId($record);

                                if ($unitId === null) {
                                    return;
                                }

                                if (Cpl::query()->where('academic_unit_id', $unitId)->where('kode', $value)->exists()) {
                                    $fail('Kode sudah dipakai CPL lain pada unit ini.');

                                    return;
                                }

                                if (CplKodeOverride::query()
                                    ->where('academic_unit_id', $unitId)
                                    ->where('kode', $value)
                                    ->where('cpl_id', '!=', $record?->id)
                                    ->exists()) {
                                    $fail('Kode sudah dipakai sebagai alias CPL lain pada unit ini.');
                                }
                            }),

                        RichEditor::make('deskripsi')
                            ->label('Deskripsi')
                            ->required(fn (?Cpl $record): bool => ! $isAsing($record))
                            ->disabled($isAsing)
                            ->dehydrated(fn (?Cpl $record): bool => ! $isAsing($record))
                            ->columnSpanFull(),

                        Select::make('domain')
                            ->label('Domain')
                            ->options(static::domainOptions())
                            ->multiple()
                            ->disabled($isAsing)
                            ->dehydrated(fn (?Cpl $record): bool => ! $isAsing($record)),
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
                Split::make([
                    TextColumn::make('kode')
                        ->label('Kode')
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::Bold)
                        ->state(function (Cpl $record): string {
                            $unitId = KurikulumTerpilih::current()?->academic_unit_id;

                            return $unitId === null
                                ? $record->kode
                                : CplBokAdaptasiScope::displayKodeMapCpl(collect([$record]), $unitId)->first();
                        }),

                    TextColumn::make('domain')
                        ->label('Domain')
                        ->badge()
                        ->formatStateUsing(fn (string $state): string => static::domainOptions()[$state] ?? $state)
                        ->placeholder('—'),
                ]),

                TextColumn::make('deskripsi')
                    ->label('Deskripsi')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? Str::limit(trim(strip_tags($state)), 100)
                        : '—')
                    ->size('sm')
                    ->color('gray'),

                TextColumn::make('unit_asal')
                    ->label('')
                    ->state(function (Cpl $record): string {
                        $unitId = KurikulumTerpilih::current()?->academic_unit_id;

                        if ($unitId === null || $record->academic_unit_id === $unitId) {
                            return '';
                        }

                        $record->loadMissing('academicUnit');

                        return 'Adaptasi dari '.($record->academicUnit->nama_lengkap ?? '—');
                    })
                    ->size('sm')
                    ->color('warning'),
            ],
            fn (Builder $query, Kurikulum $kurikulum): Builder => CplBokAdaptasiScope::scopeVisibleCpl(
                $query,
                $kurikulum->id,
            ),
        );
    }

    public static function getRelations(): array
    {
        return [];
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
