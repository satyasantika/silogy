<?php

namespace App\Modules\MK\Filament\Resources;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasKurikulumTerpilihFilter;
use App\Modules\Kurikulum\Filament\Support\Concerns\HasTimKurikulumUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\CreateMkUnit;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\EditMkUnit;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\ListMkUnits;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Policies\MkUnitPolicy;
use App\Modules\MK\Services\MkUnitTarikKontrakService;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\MK\Support\SemesterKontrakPenawaran;
use App\Support\Filament\DelegasiMenu;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\In;
use Illuminate\Validation\Rules\Unique;

class MkUnitResource extends Resource
{
    use HasKurikulumTerpilihFilter;
    use HasTimKurikulumUnitScope;

    protected static ?string $model = MkUnit::class;

    protected static ?string $policy = MkUnitPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Penawaran MK';

    protected static ?string $modelLabel = 'penawaran MK';

    protected static ?string $pluralModelLabel = 'penawaran MK';

    protected static ?string $slug = 'mk-units';

    protected static function kurikulumBannerCatatan(): ?string
    {
        return 'Penawaran MK yang tampil, diadaptasi, maupun diperbarui massal mengikuti kurikulum prodi ini.';
    }

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        return static::canAccess();
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if ($user instanceof User && PenawaranMkScope::isKoordinatorMkOnly($user)) {
            return false;
        }

        return $user instanceof User && app(MkUnitPolicy::class)->viewAny($user);
    }

    /**
     * @return Collection<int, string>
     */
    public static function scopedTimKurikulumUnitIds(): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return collect();
        }

        return AcademicUnitScope::scopedMkUnitProdiIdsFor($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['mk.academicUnit', 'academicUnit']);
        $user = Auth::user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->hasRole('Super Admin')) {
            return $query;
        }

        if (PenawaranMkScope::isKoordinatorMkOnly($user)) {
            return PenawaranMkScope::penawaranMkQuery($user);
        }

        return static::scopeEloquentByTimKurikulumUnits($query);
    }

    /**
     * MK yang dapat diadaptasi unit penawaran: milik unit sendiri
     * atau milik unit induknya (fakultas/universitas).
     *
     * @return array<string, string>
     */
    public static function adaptableMkOptions(?string $unitId): array
    {
        if (blank($unitId)) {
            return [];
        }

        $unit = AcademicUnit::query()->find($unitId);

        if (! $unit) {
            return [];
        }

        $ids = AcademicUnitScope::ancestorIdsIncludingSelf($unit);

        return Mk::query()
            ->whereIn('academic_unit_id', $ids)
            ->where('is_active', true)
            ->with('academicUnit')
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Mk $mk): array => [
                $mk->id => "{$mk->nama} — {$mk->academicUnit?->nama_lengkap}",
            ])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        $unitIds = static::scopedTimKurikulumUnitIds();

        return $schema
            ->components([
                Section::make('Adaptasi / Penawaran MK')
                    ->description('Pilih MK milik unit sendiri atau unit induk (fakultas/universitas), lalu tentukan kode dan semester pelaksanaannya di unit Anda.')
                    ->schema([
                        Select::make('academic_unit_id')
                            ->label('Unit penawaran')
                            ->options(static::timKurikulumUnitOptions())
                            ->searchable()
                            ->required()
                            ->default($unitIds->count() === 1 ? $unitIds->first() : null)
                            ->disabled($unitIds->count() === 1)
                            ->dehydrated()
                            ->live()
                            ->rule(fn (): In => Rule::in(static::scopedTimKurikulumUnitIds()->all()))
                            ->afterStateUpdated(fn (Set $set) => $set('mk_id', null)),

                        Select::make('mk_id')
                            ->label('Mata kuliah')
                            ->helperText('Termasuk MK penciri universitas/fakultas dari unit induk.')
                            ->options(fn (Get $get): array => static::adaptableMkOptions($get('academic_unit_id')))
                            ->searchable()
                            ->required()
                            ->rule(fn (Get $get): In => Rule::in(
                                array_keys(static::adaptableMkOptions($get('academic_unit_id'))),
                            ))
                            ->rule(fn (Get $get, ?MkUnit $record): Unique => (new Unique('mk_units', 'mk_id'))
                                ->where('kurikulum_id', $record?->kurikulum_id ?? KurikulumTerpilih::currentId())
                                ->ignore($record?->id))
                            ->validationMessages([
                                'unique' => 'MK ini sudah ditawarkan pada kurikulum tersebut.',
                            ]),

                        TextInput::make('kode')
                            ->label('Kode MK di unit')
                            ->required()
                            ->maxLength(20)
                            ->rule(fn (Get $get, ?MkUnit $record): Unique => (new Unique('mk_units', 'kode'))
                                ->where('kurikulum_id', $record?->kurikulum_id ?? KurikulumTerpilih::currentId())
                                ->ignore($record?->id)),

                        TextInput::make('semester_ke')
                            ->label('Semester ke-')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(14),

                        Toggle::make('is_active')
                            ->label('Aktif')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return static::applyKurikulumTerpilihTable(
            $table
                ->extraAttributes([
                    'class' => 'silogy-mk-semester-toolbar',
                ])
                ->columns([
                    TextColumn::make('mk.nama')->label('Mata kuliah')->searchable(),
                    TextColumn::make('kode')->label('Kode')->sortable(),
                    TextColumn::make('semester_ke')->label('Semester ke-')->sortable(),
                    TextColumn::make('jumlah_mahasiswa_kontrak')
                        ->label('Mahasiswa kontrak')
                        ->numeric()
                        ->alignEnd()
                        ->default(0)
                        ->sortable(query: function (Builder $query, string $direction): Builder {
                            return $query->orderBy('jumlah_mahasiswa_kontrak', $direction);
                        }),
                    IconColumn::make('is_active')->label('Aktif')->boolean(),
                ])
                ->filters([
                    static::semesterKontrakPenawaranFilter(),
                ])
                ->filtersLayout(FiltersLayout::AboveContent)
                ->filtersFormColumns(1)
                ->deferFilters(false)
                ->recordActions([
                    Action::make('tarikKontrak')
                        ->label('Tarik data')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->color('primary')
                        ->visible(fn (MkUnit $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->action(function (MkUnit $record, $livewire): void {
                            $semesterId = SemesterKontrakPenawaran::currentId();
                            $semester = filled($semesterId)
                                ? Semester::query()->find($semesterId)
                                : null;

                            if (! $semester instanceof Semester) {
                                Notification::make()
                                    ->title('Pilih semester terlebih dahulu')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $service = app(MkUnitTarikKontrakService::class);
                            $result = $service->tarik($record, $semester);
                            $service->kirimNotifikasi($result);

                            $livewire->resetTable();
                        }),
                ]),
            fn (Builder $query, Kurikulum $kurikulum): Builder => static::withJumlahMahasiswaKontrak(
                $query->where('kurikulum_id', $kurikulum->id),
            ),
        );
    }

    /**
     * Subquery jumlah mahasiswa kontrak pada semester penawaran terpilih.
     * Tidak memfilter baris MkUnit — hanya menambah kolom hitungan.
     *
     * @param  Builder<MkUnit>  $query
     * @return Builder<MkUnit>
     */
    protected static function withJumlahMahasiswaKontrak(Builder $query): Builder
    {
        $semesterId = SemesterKontrakPenawaran::currentId();

        return $query->addSelect([
            'jumlah_mahasiswa_kontrak' => DB::table('kelas_mk_mahasiswa')
                ->join('kelas_mk', 'kelas_mk.id', '=', 'kelas_mk_mahasiswa.kelas_mk_id')
                ->whereColumn('kelas_mk.mk_unit_id', 'mk_units.id')
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

                // Filter hanya menyimpan session; baris MkUnit tetap ditampilkan.
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

    public static function getPages(): array
    {
        return [
            'index' => ListMkUnits::route('/'),
            'create' => CreateMkUnit::route('/create'),
            'edit' => EditMkUnit::route('/{record}/edit'),
        ];
    }
}
