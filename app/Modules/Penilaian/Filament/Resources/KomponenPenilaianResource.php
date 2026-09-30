<?php

namespace App\Modules\Penilaian\Filament\Resources;

use App\Models\User;
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
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
use App\Modules\Penilaian\Policies\KomponenPenilaianPolicy;
use App\Modules\Penilaian\Rules\BobotKomponenSama100Rule;
use App\Modules\Penilaian\Services\RencanaEvaluasiService;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
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

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Mata Kuliah');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('komponen-penilaian', 4);
    }

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
            ->with(['mk', 'semesters', 'evaluasi', 'subcpmkKomponens.subcpmk']);

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

        $mkIds = static::scopedKoordinatorMkIds($user);

        if ($mkIds->isEmpty() && ! $user->hasRole('Admin')) {
            return $query->whereRaw('1 = 0');
        }

        if ($mkIds->isNotEmpty()) {
            return $query->whereIn('mk_id', $mkIds);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        $mkOptions = static::scopedKoordinatorMkOptions();
        $mkTerpilih = MkTerpilih::currentId();
        $semesterTerpilih = SemesterTerpilih::currentId($mkTerpilih);

        return $schema
            ->components([
                Placeholder::make('peringatan_dipakai_ulang')
                    ->hiddenLabel()
                    ->content(fn (?KomponenPenilaian $record): HtmlString => static::peringatanDipakaiUlang($record))
                    ->visible(fn (?KomponenPenilaian $record): bool => $record !== null
                        && $record->semesters()->count() > 1)
                    ->columnSpanFull(),
                Section::make('Komponen Penilaian')
                    ->schema([
                        Select::make('mk_id')
                            ->label('Mata Kuliah')
                            ->options(fn (?KomponenPenilaian $record): array => static::mkOptionsUntukForm(
                                $mkOptions,
                                $record,
                            ))
                            ->searchable()
                            ->required()
                            ->default(fn (?KomponenPenilaian $record): ?string => $record?->mk_id
                                ?? $mkTerpilih
                                ?? (count($mkOptions) === 1 ? array_key_first($mkOptions) : null))
                            ->disabled(fn (?KomponenPenilaian $record): bool => $record !== null
                                || (filled($mkTerpilih) && array_key_exists($mkTerpilih, $mkOptions)))
                            ->live()
                            ->dehydrated(),

                        Select::make('semester_id')
                            ->label('Semester')
                            ->options(SemesterTerpilih::optionsSemua())
                            ->searchable()
                            ->required()
                            // Untuk record yang sudah ada, nilainya diisi
                            // EditKomponenPenilaian::mutateFormDataBeforeFill()
                            // dari pivot semester — bukan dari kolom baris.
                            ->default(fn (): ?string => $semesterTerpilih ?? SemesterTerpilih::defaultId())
                            ->disabled(fn (?KomponenPenilaian $record): bool => $record !== null || filled($semesterTerpilih))
                            ->dehydrated()
                            // Bukan kolom model lagi: semester dan bobot ditulis
                            // ke komponen_penilaian_semester oleh Create/Edit page.
                            ->helperText('Asesmen ini berlaku untuk semua kelas pada mata kuliah dan semester ini.'),

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
                Stack::make([
                    Split::make([
                        TextColumn::make('kode')
                            ->label('Kode')
                            ->searchable()
                            ->placeholder('—')
                            ->weight(FontWeight::Bold),

                        TextColumn::make('bobot')
                            ->label('Bobot (%)')
                            ->suffix('%')
                            ->getStateUsing(function (KomponenPenilaian $record): ?float {
                                $semesterId = static::semesterKonteks();

                                return $semesterId === null ? null : $record->bobotUntukSemester($semesterId);
                            })
                            ->icon('heroicon-o-pencil-square')
                            ->iconPosition(IconPosition::After)
                            ->disabledClick(fn (KomponenPenilaian $record): bool => ! Auth::user()?->can('update', $record))
                            ->action(static::editBobotAction()),
                    ]),

                    TextColumn::make('nama')
                        ->label('Nama penugasan')
                        ->searchable()
                        ->wrap()
                        ->weight(FontWeight::Bold),

                    TextColumn::make('evaluasi.nama')
                        ->label('Komponen')
                        ->size('sm')
                        ->color('gray'),

                    TextColumn::make('subcpmk_terpetakan')
                        ->label('SubCPMK')
                        ->size('sm')
                        // Jangan ikutkan klik kolom ke recordUrl (edit asesmen).
                        ->disabledClick()
                        ->getStateUsing(function (KomponenPenilaian $record): string|HtmlString {
                            $service = app(RencanaEvaluasiService::class);

                            $semesterKonteks = static::semesterKonteks();

                            $items = $record->subcpmkKomponens
                                ->when(
                                    $semesterKonteks !== null,
                                    fn ($pivots) => $pivots->where('semester_id', $semesterKonteks),
                                )
                                ->filter(fn ($pivot) => $pivot->subcpmk !== null)
                                ->sortBy(fn ($pivot) => $pivot->subcpmk->kode)
                                ->map(function ($pivot) use ($service): string {
                                    $sub = $pivot->subcpmk;
                                    $kodeHtml = view(
                                        'filament.modules.kurikulum.partials.kode-keterangan-trigger',
                                        [
                                            'jenis' => 'Sub-CPMK',
                                            'kode' => $sub->kode,
                                            'deskripsi' => $sub->deskripsi,
                                        ],
                                    )->render();

                                    return sprintf(
                                        '<div style="display:flex;align-items:baseline;gap:6px;flex-wrap:wrap;">%s<span style="color:#2563eb;font-weight:600;">(%s)</span></div>',
                                        $kodeHtml,
                                        e($service->formatBobot((float) $pivot->bobot)),
                                    );
                                })
                                ->values();

                            if ($items->isEmpty()) {
                                return '—';
                            }

                            // HtmlString: Alpine/data-silogy tidak kena sanitizeHtml Filament.
                            return new HtmlString($items->join(''));
                        }),
                ])->space(2),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginated(false)
            ->extraAttributes([
                'class' => 'silogy-mk-semester-toolbar',
            ])
            ->filters([
                static::semesterTerpilihFilter(
                    fn (Builder $query, string $semesterId): Builder => KomponenPenilaian::saringSemester($query, $semesterId),
                    ['indikator' => false, 'labelTersembunyi' => true],
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

                    return $query->where('mk_id', $mkId);
                }

                $semesterId = SemesterTerpilih::currentId($mkId);

                if (blank($mkId) || blank($semesterId)) {
                    return $query->whereRaw('1 = 0');
                }

                return KomponenPenilaian::saringSemester($query->where('mk_id', $mkId), $semesterId);
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
     * Asesmen yang dipakai ulang adalah baris yang SAMA. Nama dan jenis
     * evaluasinya berlaku untuk semua semester yang memakainya; hanya bobot
     * dan pemetaan Sub-CPMK yang per semester.
     */
    protected static function peringatanDipakaiUlang(?KomponenPenilaian $record): HtmlString
    {
        if ($record === null) {
            return new HtmlString('');
        }

        $semester = $record->semesters()
            ->orderBy('kode')
            ->pluck('nama')
            ->join(', ');

        return new HtmlString(
            '<div class="rounded-lg border border-warning-600/40 bg-warning-50 p-4 text-sm '
            .'text-warning-900 dark:border-warning-500/40 dark:bg-warning-950/40 dark:text-warning-100">'
            .'<p class="font-semibold">Asesmen ini dipakai di lebih dari satu semester.</p>'
            .'<p class="mt-1">Perubahan kode, nama, dan jenis evaluasi berlaku untuk semua semester '
            .'berikut: <strong>'.e($semester).'</strong>. Bobotnya sendiri tetap milik masing-masing '
            .'semester, jadi mengubahnya di sini hanya memengaruhi semester yang sedang dipilih.</p>'
            .'</div>',
        );
    }

    /**
     * Semester yang sedang menjadi konteks daftar/form. Bobot Asesmen hanya
     * bermakna berpasangan dengan semester, jadi tanpa ini tidak ada angka
     * yang benar untuk ditampilkan.
     */
    protected static function semesterKonteks(): ?string
    {
        $mkId = MkTerpilih::currentId();
        $semesterId = SemesterTerpilih::currentId($mkId) ?? SemesterTerpilih::defaultId();

        return filled($semesterId) ? (string) $semesterId : null;
    }

    protected static function editBobotAction(): Action
    {
        return Action::make('editBobot')
            ->label('Edit Bobot')
            ->modalHeading('Edit Bobot (%)')
            ->modalSubmitActionLabel('Simpan')
            ->authorize('update')
            ->schema([
                TextInput::make('bobot')
                    ->label('Bobot (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->step(0.01)
                    ->required(),
            ])
            ->fillForm(fn (KomponenPenilaian $record): array => [
                'bobot' => static::semesterKonteks() === null
                    ? null
                    : $record->bobotUntukSemester((string) static::semesterKonteks()),
            ])
            ->action(function (array $data, KomponenPenilaian $record): void {
                $semesterId = static::semesterKonteks();

                if ($semesterId === null) {
                    return;
                }

                $bobot = min(max((float) $data['bobot'], 0), 100);

                // Bobot milik pasangan (asesmen, semester): mengubahnya di sini
                // tidak boleh menyentuh semester lain yang memakai asesmen sama.
                KomponenPenilaianSemester::query()->updateOrCreate(
                    [
                        'komponen_penilaian_id' => $record->id,
                        'semester_id' => $semesterId,
                    ],
                    ['bobot' => $bobot],
                );
            });
    }

    /**
     * Opsi Mata Kuliah untuk form; pastikan MK milik record yang sedang
     * diedit selalu tersedia walau berada di luar cakupan terkini.
     *
     * @param  array<string, string>  $mkOptions
     * @return array<string, string>
     */
    protected static function mkOptionsUntukForm(array $mkOptions, ?KomponenPenilaian $record): array
    {
        if ($record === null || blank($record->mk_id) || array_key_exists($record->mk_id, $mkOptions)) {
            return $mkOptions;
        }

        $record->loadMissing('mk');

        if ($record->mk === null) {
            return $mkOptions;
        }

        return [$record->mk_id => $record->mk->nama] + $mkOptions;
    }

    /**
     * Ringkasan bobot komponen secara realtime, termasuk nilai bobot yang
     * sedang diisi (belum tersimpan), dihitung terhadap mata kuliah +
     * semester yang sedang dipilih pada form.
     */
    protected static function bobotHelperText(Get $get, ?KomponenPenilaian $record): HtmlString
    {
        $mkId = $record?->mk_id ?? $get('mk_id');
        $semesterId = $get('semester_id') ?? static::semesterKonteks();

        if (blank($mkId) || blank($semesterId)) {
            return new HtmlString('Pilih mata kuliah dan semester untuk melihat total bobot komponen.');
        }

        $pending = is_numeric($get('bobot')) ? (float) $get('bobot') : 0.0;

        // Kecualikan berdasar kode TERSIMPAN (sebelum diedit), bukan kode
        // baru yang mungkin sedang diganti — agar baris lama tidak ikut
        // terhitung ganda bersama nilai bobot yang baru diisi.
        $kode = $record?->kode ?? (filled($get('kode')) ? (string) $get('kode') : null);

        $total = BobotKomponenSama100Rule::totalBobot((string) $mkId, (string) $semesterId, $kode, $pending);

        $sudahPas = abs(100 - $total) < 0.01;
        $selisih = round(100 - $total, 2);
        $color = $sudahPas ? '#166534' : '#92400e';

        $keterangan = $sudahPas
            ? sprintf('Total bobot komponen pada mata kuliah dan semester ini: %.2f%% — sudah pas 100%%.', $total)
            : sprintf(
                'Total bobot komponen pada mata kuliah dan semester ini: %.2f%% dari 100%% (%s %.2f%%).',
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
