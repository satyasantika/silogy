<?php

namespace App\Modules\MK\Filament\Resources;

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumTerpilihFilter;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasTimKurikulumUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Filament\Resources\MkResource\Pages\CreateMk;
use App\Modules\MK\Filament\Resources\MkResource\Pages\EditMk;
use App\Modules\MK\Filament\Resources\MkResource\Pages\ListMks;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Support\SemesterKontrakPenawaran;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class MkResource extends Resource
{
    use HasKurikulumTerpilihFilter;
    use HasTimKurikulumUnitScope;

    protected static ?string $model = Mk::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Kurikulum');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('mata-kuliah', 5);
    }

    protected static ?string $modelLabel = 'mata kuliah';

    protected static ?string $pluralModelLabel = 'mata kuliah';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $slug = 'mks';

    protected static function kurikulumBannerCatatan(): ?string
    {
        return 'Mata kuliah yang tampil, ditambahkan, maupun diimpor mengikuti kurikulum ini.';
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
    public static function jenisOptions(): array
    {
        return [
            'wajib' => 'Wajib',
            'pilihan' => 'Pilihan',
            'praktikum' => 'Praktikum',
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
                Section::make('Data Mata Kuliah')
                    ->schema([
                        Select::make('academic_unit_id')
                            ->label('Unit pemilik MK')
                            ->options(static::timKurikulumUnitOptions())
                            ->searchable()
                            ->required()
                            ->default($unitIds->count() === 1 ? $unitIds->first() : null)
                            ->disabled($unitIds->count() === 1)
                            ->dehydrated(),

                        Placeholder::make('state_display')
                            ->label('State workflow MK')
                            ->content(fn (?Mk $record): string => strtoupper($record?->state ?? 'draft'))
                            ->visible(fn (?Mk $record): bool => $record !== null),

                        TextInput::make('nama')
                            ->label('Nama mata kuliah')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),

                        TextInput::make('sks_teori')
                            ->label('SKS teori')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(24)
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::syncSks($get, $set)),

                        TextInput::make('sks_praktik')
                            ->label('SKS praktik')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(24)
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::syncSks($get, $set)),

                        TextInput::make('sks_lapangan')
                            ->label('SKS lapangan')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(24)
                            ->default(0)
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set) => static::syncSks($get, $set)),

                        TextInput::make('sks')
                            ->label('Total SKS')
                            ->numeric()
                            ->disabled()
                            ->dehydrated()
                            ->default(0),

                        Select::make('jenis')
                            ->label('Jenis')
                            ->options(static::jenisOptions())
                            ->required()
                            ->default('wajib'),

                        Select::make('koordinator_mk_id')
                            ->label('Koordinator MK')
                            ->helperText('Pilih dari dosen pengampu. Menetapkan koordinator otomatis memberi role Koordinator Mata Kuliah.')
                            ->options(fn (): array => static::koordinatorMkOptions())
                            ->searchable()
                            ->nullable(),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function syncSks(Get $get, Set $set): void
    {
        $total = (int) $get('sks_teori') + (int) $get('sks_praktik') + (int) $get('sks_lapangan');
        $set('sks', $total);
    }

    /**
     * Opsi koordinator: seluruh user ber-role Dosen Pengampu, label
     * "Nama (kode_prodi)". Kode dari unit prodi penugasan; bila belum ada
     * prodi, pakai kode unit pertama.
     *
     * @return array<string, string>
     */
    public static function koordinatorMkOptions(): array
    {
        return User::query()
            ->whereIn('id', ActiveRole::userIdsWithRoleName('Dosen Pengampu'))
            ->with(['academicUnits' => fn ($query) => $query->orderBy('nama')])
            ->orderBy('full_name')
            ->get()
            ->mapWithKeys(fn (User $user): array => [
                $user->id => static::labelKoordinatorMk($user),
            ])
            ->all();
    }

    public static function labelKoordinatorMk(User $user): string
    {
        $nama = $user->full_name ?? $user->username ?? 'Tanpa nama';

        $kodeProdi = $user->academicUnits
            ->first(fn (AcademicUnit $unit): bool => $unit->isProdi())
            ?->code
            ?? $user->academicUnits->first()?->code;

        return filled($kodeProdi) ? "{$nama} ({$kodeProdi})" : $nama;
    }

    /**
     * @return array<string, string>
     */
    public static function descendantUnitOptionsForMk(Mk $mk): array
    {
        $ids = AcademicUnitScope::descendantIdsIncludingSelf($mk->academic_unit_id);

        return AcademicUnit::query()
            ->whereIn('id', $ids)
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (AcademicUnit $unit): array => [$unit->id => $unit->nama_lengkap])
            ->all();
    }

    public static function table(Table $table): Table
    {
        return static::applyKurikulumTerpilihTable(
            $table
                ->extraAttributes([
                    'class' => 'silogy-mk-semester-toolbar',
                ])
                ->columns([
                    TextColumn::make('nama')->label('Nama')->searchable()->sortable(),
                    TextColumn::make('sks')->label('SKS')->sortable(),
                    TextColumn::make('jenis')
                        ->label('Jenis')
                        ->formatStateUsing(fn (string $state): string => static::jenisOptions()[$state] ?? $state),
                    TextColumn::make('state')->label('State')->badge(),
                    SelectColumn::make('koordinator_mk_id')
                        ->label('Koordinator MK')
                        ->options(fn (): array => static::koordinatorMkOptions())
                        ->searchableOptions()
                        ->placeholder('—')
                        ->selectablePlaceholder()
                        ->getOptionLabelUsing(function ($value): ?string {
                            if (blank($value)) {
                                return null;
                            }

                            $user = User::query()->with('academicUnits')->find($value);

                            return $user !== null ? static::labelKoordinatorMk($user) : null;
                        })
                        ->disabled(fn (Mk $record): bool => Gate::denies('update', $record))
                        ->rules(['nullable', 'uuid']),
                    TextColumn::make('dikontrak')
                        ->label('Dikontrak')
                        ->html()
                        ->getStateUsing(fn (Mk $record): HtmlString => static::dikontrakBadgeHtml($record))
                        ->sortable(query: function (Builder $query, string $direction): Builder {
                            return $query->orderBy('jumlah_mahasiswa', $direction);
                        }),
                    IconColumn::make('is_active')->label('Aktif')->boolean(),
                ])
                ->filters([
                    static::semesterKontrakPenawaranFilter(),
                ])
                ->filtersLayout(FiltersLayout::AboveContent)
                ->filtersFormColumns(1)
                ->deferFilters(false)
                ->recordActions([])
                ->toolbarActions([
                    BulkActionGroup::make([
                        DeleteBulkAction::make(),
                    ]),
                ]),
            fn (Builder $query, Kurikulum $kurikulum): Builder => static::withJumlahKelasDanMahasiswa(
                $query->where('kurikulum_id', $kurikulum->id),
            ),
        );
    }

    /**
     * Badge ringkas total kelas & mahasiswa kontrak (via penawaran MK).
     */
    protected static function dikontrakBadgeHtml(Mk $record): HtmlString
    {
        $kelas = (int) ($record->jumlah_kelas ?? 0);
        $mahasiswa = (int) ($record->jumlah_mahasiswa ?? 0);

        if ($kelas === 0 && $mahasiswa === 0) {
            return new HtmlString('<span class="silogy-dikontrak-empty" aria-hidden="true">—</span>');
        }

        $chip = static function (int $nilai, string $label, string $varian): string {
            return sprintf(
                '<span class="silogy-dikontrak-chip silogy-dikontrak-%s" title="%s %s">'
                .'<span class="silogy-dikontrak-n">%d</span>'
                .'<span class="silogy-dikontrak-l">%s</span>'
                .'</span>',
                e($varian),
                e((string) $nilai),
                e($label),
                $nilai,
                e($label),
            );
        };

        return new HtmlString(
            '<span class="silogy-dikontrak">'
            .$chip($kelas, 'kelas', 'kelas')
            .$chip($mahasiswa, 'mahasiswa', 'mhs')
            .'</span>'
        );
    }

    /**
     * Hitung kelas & mahasiswa lewat seluruh penawaran MK (mk_units) untuk
     * mk.id — lintas prodi/kurikulum — pada semester kontrak penawaran terpilih.
     * Tidak memfilter baris Mk.
     *
     * @param  Builder<Mk>  $query
     * @return Builder<Mk>
     */
    protected static function withJumlahKelasDanMahasiswa(Builder $query): Builder
    {
        $semesterId = SemesterKontrakPenawaran::currentId();

        return $query->addSelect([
            'jumlah_kelas' => DB::table('kelas_mk')
                ->join('mk_units', 'mk_units.id', '=', 'kelas_mk.mk_unit_id')
                ->whereColumn('mk_units.mk_id', 'mk.id')
                ->when(
                    filled($semesterId),
                    fn ($builder) => $builder->where('kelas_mk.semester_id', $semesterId),
                    fn ($builder) => $builder->whereRaw('0 = 1'),
                )
                ->selectRaw('count(*)'),
            'jumlah_mahasiswa' => DB::table('kelas_mk_mahasiswa')
                ->join('kelas_mk', 'kelas_mk.id', '=', 'kelas_mk_mahasiswa.kelas_mk_id')
                ->join('mk_units', 'mk_units.id', '=', 'kelas_mk.mk_unit_id')
                ->whereColumn('mk_units.mk_id', 'mk.id')
                ->when(
                    filled($semesterId),
                    fn ($builder) => $builder->where('kelas_mk.semester_id', $semesterId),
                    fn ($builder) => $builder->whereRaw('0 = 1'),
                )
                ->selectRaw('count(*)'),
        ]);
    }

    protected static function semesterKontrakPenawaranFilter(): SelectFilter
    {
        return SelectFilter::make('semester_kontrak_penawaran')
            ->label('Semester')
            ->default(fn (): ?string => SemesterKontrakPenawaran::currentId())
            ->selectablePlaceholder(false)
            ->columnSpanFull()
            ->schema([
                Select::make('value')
                    ->label('Semester')
                    ->hiddenLabel()
                    ->prefix('Semester')
                    ->options(fn (): array => SemesterKontrakPenawaran::options())
                    ->default(fn (): ?string => SemesterKontrakPenawaran::currentId())
                    ->selectablePlaceholder(false)
                    ->native(true)
                    ->searchable(false)
                    ->live()
                    ->afterStateHydrated(function (Select $component, $state): void {
                        $resolved = static::resolveSemesterKontrakFilterState($state);

                        if (blank($resolved)) {
                            return;
                        }

                        $component->state($resolved);
                        SemesterKontrakPenawaran::set($resolved);
                    })
                    ->afterStateUpdated(function (Select $component, $state): void {
                        $resolved = static::resolveSemesterKontrakFilterState($state);

                        if (blank($resolved)) {
                            return;
                        }

                        if ($resolved !== $state) {
                            $component->state($resolved);
                        }

                        SemesterKontrakPenawaran::set($resolved);
                    }),
            ])
            ->query(function (Builder $query, array $data): Builder {
                $semesterId = static::resolveSemesterKontrakFilterState($data['value'] ?? null);

                if (filled($semesterId)) {
                    SemesterKontrakPenawaran::set($semesterId);
                }

                // Filter hanya menyimpan session; baris Mk tetap ditampilkan.
                return $query;
            })
            ->indicateUsing(fn (): array => []);
    }

    protected static function resolveSemesterKontrakFilterState(mixed $state = null): ?string
    {
        $options = SemesterKontrakPenawaran::options();

        if ($options === []) {
            return null;
        }

        $candidate = filled($state) ? (string) $state : SemesterKontrakPenawaran::currentId();

        if (filled($candidate) && array_key_exists($candidate, $options)) {
            return $candidate;
        }

        return SemesterKontrakPenawaran::defaultId();
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMks::route('/'),
            'create' => CreateMk::route('/create'),
            'edit' => EditMk::route('/{record}/edit'),
        ];
    }
}
