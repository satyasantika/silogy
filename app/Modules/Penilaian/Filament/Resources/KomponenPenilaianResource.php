<?php

namespace App\Modules\Penilaian\Filament\Resources;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Filament\Support\Concerns\HasSemesterTerpilihFilter;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\CreateKomponenPenilaian;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\EditKomponenPenilaian;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\ListKomponenPenilaians;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\RelationManagers\SubcpmkKomponenPenilaianRelationManager;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Policies\KomponenPenilaianPolicy;
use App\Support\Filament\DelegasiMenu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class KomponenPenilaianResource extends Resource
{
    use HasKoordinatorMkScope;
    use HasSemesterTerpilihFilter;

    protected static ?string $model = KomponenPenilaian::class;

    protected static ?string $policy = KomponenPenilaianPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|\UnitEnum|null $navigationGroup = 'Mata Kuliah';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Asesmen';

    protected static ?string $modelLabel = 'asesmen';

    protected static ?string $pluralModelLabel = 'asesmen';

    protected static ?string $slug = 'komponen-penilaian';

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && app(KomponenPenilaianPolicy::class)->viewAny($user)
            && (! PenawaranMkScope::isKoordinatorMkOnly($user) || MkTerpilih::current() !== null);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['kelasMk.mkUnit.mk', 'evaluasi', 'subcpmkKomponens.subcpmk']);

        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return $query;
        }

        if ($user->hasRole('Dosen Pengampu') && ! $user->hasRole('Admin')) {
            return $query->whereRaw('1 = 0');
        }

        $kelasIds = static::scopedKoordinatorKelasMkIds($user);

        if ($kelasIds->isEmpty() && ! $user->hasRole('Admin')) {
            return $query->whereRaw('1 = 0');
        }

        if ($kelasIds->isNotEmpty()) {
            return $query->whereIn('kelas_mk_id', $kelasIds);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $mkTerpilih = MkTerpilih::currentId();
        $kelasOptions = static::scopedKoordinatorKelasMkOptionsForMk($mkTerpilih);

        return $schema
            ->components([
                Section::make('Komponen Penilaian')
                    ->schema([
                        Select::make('kelas_mk_id')
                            ->label('Kelas MK')
                            ->options($kelasOptions)
                            ->searchable()
                            ->required()
                            ->default(count($kelasOptions) === 1 ? array_key_first($kelasOptions) : null)
                            ->disabled(filled($mkTerpilih) && count($kelasOptions) === 1)
                            ->dehydrated(),

                        Select::make('evaluasi_id')
                            ->label('Jenis evaluasi')
                            ->options(fn (): array => Evaluasi::query()
                                ->orderBy('kode')
                                ->pluck('nama', 'id')
                                ->all())
                            ->searchable()
                            ->required()
                            ->preload(),

                        TextInput::make('kode')
                            ->label('Kode')
                            ->maxLength(30),

                        TextInput::make('nama')
                            ->label('Nama')
                            ->required()
                            ->maxLength(100),

                        TextInput::make('bobot')
                            ->label('Bobot (%)')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(100)
                            ->required()
                            ->helperText('Total bobot semua komponen per kelas wajib 100%.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->description(fn (): HtmlString => MkTerpilih::bannerHtml())
            ->columns([
                TextColumn::make('kode')
                    ->label('Kode asesmen')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('nama')
                    ->label('Nama tugas')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                TextColumn::make('bobot')
                    ->label('Bobot tugas (%)')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('evaluasi.nama')
                    ->label('Komponen penilaian')
                    ->sortable(),

                TextColumn::make('subcpmk_terpetakan')
                    ->label('Kode Sub-CPMK terpetakan')
                    ->getStateUsing(function (KomponenPenilaian $record): string {
                        $kodes = $record->subcpmkKomponens
                            ->map(fn ($pivot) => $pivot->subcpmk?->kode)
                            ->filter()
                            ->values();

                        return $kodes->isNotEmpty() ? $kodes->join(', ') : '—';
                    }),

                TextColumn::make('kelasMk.kode_kelas')
                    ->label('Kelas')
                    ->formatStateUsing(fn (KomponenPenilaian $record): string => $record->kelasMk?->kode_kelas ?? '—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('kelasMk.semester.kode')
                    ->label('Semester')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                static::semesterTerpilihFilter(
                    fn (Builder $query, string $semesterId): Builder => $query->whereHas(
                        'kelasMk',
                        fn (Builder $kelasQuery): Builder => $kelasQuery->where('semester_id', $semesterId),
                    ),
                ),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(1)
            ->deferFilters(false)
            ->modifyQueryUsing(function (Builder $query): Builder {
                $mkId = MkTerpilih::currentId();

                if (! SemesterTerpilih::berlakuUntukUser()) {
                    if (blank($mkId)) {
                        return $query;
                    }

                    return $query->whereHas(
                        'kelasMk',
                        fn (Builder $kelasQuery): Builder => $kelasQuery->whereHas(
                            'mkUnit',
                            fn (Builder $mkUnitQuery): Builder => $mkUnitQuery->where('mk_id', $mkId),
                        ),
                    );
                }

                $semesterId = SemesterTerpilih::currentId($mkId);

                if (blank($mkId) || blank($semesterId)) {
                    return $query->whereRaw('1 = 0');
                }

                return $query->whereHas(
                    'kelasMk',
                    fn (Builder $kelasQuery): Builder => $kelasQuery
                        ->where('semester_id', $semesterId)
                        ->whereHas(
                            'mkUnit',
                            fn (Builder $mkUnitQuery): Builder => $mkUnitQuery->where('mk_id', $mkId),
                        ),
                );
            })
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    public static function scopedKoordinatorKelasMkOptionsForMk(?string $mkId = null): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        $query = KelasMk::query()
            ->with(['mkUnit.mk', 'semester'])
            ->orderBy('kode_kelas');

        if (! $user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            $query->where('koordinator_mk_id', $user->id);
        }

        if (filled($mkId)) {
            $query->whereHas(
                'mkUnit',
                fn (Builder $mkUnitQuery): Builder => $mkUnitQuery->where('mk_id', $mkId),
            );
        }

        return $query
            ->get()
            ->mapWithKeys(fn (KelasMk $kelas): array => [
                $kelas->id => sprintf(
                    '%s – Kelas %s (%s)',
                    $kelas->mkUnit?->mk?->nama ?? '—',
                    $kelas->kode_kelas,
                    $kelas->semester?->kode ?? '—',
                ),
            ])
            ->all();
    }

    public static function getRelations(): array
    {
        return [
            SubcpmkKomponenPenilaianRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKomponenPenilaians::route('/'),
            'create' => CreateKomponenPenilaian::route('/create'),
            'edit' => EditKomponenPenilaian::route('/{record}/edit'),
        ];
    }
}
