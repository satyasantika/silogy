<?php

namespace App\Modules\Penilaian\Filament\Resources;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Filament\Support\Concerns\HasSemesterTerpilihFilter;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\CreateKomponenPenilaian;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\EditKomponenPenilaian;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\ListKomponenPenilaians;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\RelationManagers\SubcpmkKomponenPenilaianRelationManager;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Policies\KomponenPenilaianPolicy;
use App\Modules\Penilaian\Rules\BobotKomponenSama100Rule;
use App\Modules\Penilaian\Services\KomponenPenilaianMassalService;
use App\Support\Filament\DelegasiMenu;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
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
                            ->label(fn (?KomponenPenilaian $record): string => static::resolveModeMassal($record) !== null
                                ? 'Mata Kuliah'
                                : 'Kelas MK')
                            ->options(fn (?KomponenPenilaian $record): array => static::kelasFieldOptions(
                                $kelasOptions,
                                $record,
                            ))
                            ->searchable()
                            ->required()
                            ->default(fn (?KomponenPenilaian $record): ?string => static::kelasFieldDefault(
                                $kelasOptions,
                                $record,
                            ))
                            ->disabled(fn (?KomponenPenilaian $record): bool => $record !== null
                                || static::resolveModeMassal($record) !== null
                                || (filled($mkTerpilih) && count($kelasOptions) === 1))
                            ->helperText(fn (?KomponenPenilaian $record): ?string => static::resolveModeMassal($record) !== null
                                ? 'Asesmen ini berlaku untuk semua kelas pada mata kuliah dan semester ini.'
                                : null)
                            ->live()
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
                            ->required()
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
                            ->live(onBlur: false)
                            ->helperText(fn (Get $get, ?KomponenPenilaian $record): HtmlString => static::bobotHelperText($get, $record)),
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
                    ->label('Kode')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('nama')
                    ->label('Nama penugasan')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->extraCellAttributes(['style' => 'max-width:16rem;white-space:normal;word-break:break-word;'])
                    ->weight(FontWeight::Bold),

                TextColumn::make('bobot')
                    ->label('Bobot (%)')
                    ->suffix('%')
                    ->sortable(),

                TextColumn::make('evaluasi.nama')
                    ->label('Komponen')
                    ->sortable(),

                TextColumn::make('subcpmk_terpetakan')
                    ->label('SubCPMK')
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

    /**
     * Opsi Kelas MK untuk form; pastikan kelas milik record yang sedang
     * diedit selalu tersedia walau berada di luar MK terpilih saat ini.
     *
     * @param  array<string, string>  $kelasOptions
     * @return array<string, string>
     */
    protected static function kelasOptionsUntukForm(array $kelasOptions, ?KomponenPenilaian $record): array
    {
        if ($record === null || blank($record->kelas_mk_id) || array_key_exists($record->kelas_mk_id, $kelasOptions)) {
            return $kelasOptions;
        }

        $record->loadMissing('kelasMk.mkUnit.mk', 'kelasMk.semester');
        $kelas = $record->kelasMk;

        if (! $kelas instanceof KelasMk) {
            return $kelasOptions;
        }

        return [
            $kelas->id => sprintf(
                '%s – Kelas %s (%s)',
                $kelas->mkUnit?->mk?->nama ?? '—',
                $kelas->kode_kelas,
                $kelas->semester?->kode ?? '—',
            ),
        ] + $kelasOptions;
    }

    /**
     * Konteks "mode massal" (satu asesmen berlaku ke semua kelas pada MK +
     * semester) bila tersedia; null berarti tetap pakai mode lama (pilih
     * satu Kelas MK). Untuk record yang sedang diedit, konteks diturunkan
     * dari kelas MK milik record itu sendiri (bukan dari sesi terpilih).
     *
     * @return array{mk: Mk, semester_id: string, kelas: Collection<int, KelasMk>}|null
     */
    protected static function resolveModeMassal(?KomponenPenilaian $record): ?array
    {
        return $record instanceof KomponenPenilaian
            ? KomponenPenilaianMassalService::resolveUntukRecord($record)
            : KomponenPenilaianMassalService::resolveUntukPembuatan();
    }

    /**
     * @param  array<string, string>  $kelasOptions
     * @return array<string, string>
     */
    protected static function kelasFieldOptions(array $kelasOptions, ?KomponenPenilaian $record): array
    {
        $massal = static::resolveModeMassal($record);

        if ($massal === null) {
            return static::kelasOptionsUntukForm($kelasOptions, $record);
        }

        $anchorId = $record?->kelas_mk_id ?? $massal['kelas']->first()->id;
        $semesterKode = Semester::query()->whereKey($massal['semester_id'])->value('kode') ?? '—';

        return [$anchorId => sprintf('%s (%s)', $massal['mk']->nama, $semesterKode)];
    }

    /**
     * @param  array<string, string>  $kelasOptions
     */
    protected static function kelasFieldDefault(array $kelasOptions, ?KomponenPenilaian $record): ?string
    {
        $massal = static::resolveModeMassal($record);

        if ($massal !== null) {
            return $record?->kelas_mk_id ?? $massal['kelas']->first()->id;
        }

        return $record?->kelas_mk_id ?? (count($kelasOptions) === 1 ? array_key_first($kelasOptions) : null);
    }

    /**
     * Ringkasan bobot komponen secara realtime, termasuk nilai bobot yang
     * sedang diisi (belum tersimpan). Dihitung terhadap mata kuliah +
     * semester terpilih (mode massal), atau satu kelas saja bila mode
     * massal tidak tersedia.
     */
    protected static function bobotHelperText(Get $get, ?KomponenPenilaian $record): HtmlString
    {
        $kelasMkId = $get('kelas_mk_id');

        if (blank($kelasMkId)) {
            return new HtmlString('Pilih Kelas MK untuk melihat total bobot komponen.');
        }

        $massal = static::resolveModeMassal($record);
        $kelasMkIds = $massal !== null ? $massal['kelas']->pluck('id')->all() : [(string) $kelasMkId];

        $pending = is_numeric($get('bobot')) ? (float) $get('bobot') : 0.0;

        // Kecualikan berdasar kode TERSIMPAN (sebelum diedit), bukan kode
        // baru yang mungkin sedang diganti — agar baris lama tidak ikut
        // terhitung ganda bersama nilai bobot yang baru diisi.
        $kode = $record?->kode ?? (filled($get('kode')) ? (string) $get('kode') : null);

        $total = BobotKomponenSama100Rule::totalBobot($kelasMkIds, $kode, $pending);

        $sudahPas = abs(100 - $total) < 0.01;
        $selisih = round(100 - $total, 2);
        $color = $sudahPas ? '#166534' : '#92400e';
        $cakupan = $massal !== null ? 'pada mata kuliah dan semester ini' : 'pada kelas ini';

        $keterangan = $sudahPas
            ? sprintf('Total bobot komponen %s: %.2f%% — sudah pas 100%%.', $cakupan, $total)
            : sprintf(
                'Total bobot komponen %s: %.2f%% dari 100%% (%s %.2f%%).',
                $cakupan,
                $total,
                $selisih > 0 ? 'kurang' : 'lebih',
                abs($selisih),
            );

        return new HtmlString('<span style="color:'.$color.';font-weight:600;">'.e($keterangan).'</span>');
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
