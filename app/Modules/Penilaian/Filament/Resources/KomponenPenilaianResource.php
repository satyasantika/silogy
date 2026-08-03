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
            ->with(['mk', 'semester', 'evaluasi', 'subcpmkKomponens.subcpmk']);

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
                            ->default(fn (?KomponenPenilaian $record): ?string => $record?->semester_id
                                ?? $semesterTerpilih
                                ?? SemesterTerpilih::defaultId())
                            ->disabled(fn (?KomponenPenilaian $record): bool => $record !== null || filled($semesterTerpilih))
                            ->dehydrated()
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
                        ->html()
                        ->size('sm')
                        ->getStateUsing(function (KomponenPenilaian $record): string {
                            $service = app(RencanaEvaluasiService::class);

                            $items = $record->subcpmkKomponens
                                ->filter(fn ($pivot) => $pivot->subcpmk !== null)
                                ->sortBy(fn ($pivot) => $pivot->subcpmk->kode)
                                ->map(fn ($pivot) => sprintf(
                                    '<div>%s <span style="color:#2563eb;font-weight:600;">(%s)</span></div>',
                                    e($pivot->subcpmk->kode),
                                    e($service->formatBobot((float) $pivot->bobot)),
                                ))
                                ->values();

                            return $items->isNotEmpty() ? $items->join('') : '—';
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
                    fn (Builder $query, string $semesterId): Builder => $query->where('semester_id', $semesterId),
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

                return $query->where('mk_id', $mkId)->where('semester_id', $semesterId);
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
            ->fillForm(fn (KomponenPenilaian $record): array => ['bobot' => $record->bobot])
            ->action(function (array $data, KomponenPenilaian $record): void {
                $bobot = min(max((float) $data['bobot'], 0), 100);

                $record->update(['bobot' => $bobot]);
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
        $semesterId = $record?->semester_id ?? $get('semester_id');

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
