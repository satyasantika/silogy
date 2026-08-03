<?php

namespace App\Modules\MK\Filament\Resources;

use App\Models\User;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Filament\Resources\CpmkResource\RelationManagers\MkCpmkRelationManager;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\CreateSubcpmk;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\EditSubcpmk;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\ListSubcpmks;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Filament\Support\Concerns\HasMkTerpilihScope;
use App\Modules\MK\Filament\Support\Concerns\HasSemesterTerpilihFilter;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Policies\SubcpmkPolicy;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Services\RencanaEvaluasiService;
use App\Modules\Penilaian\Services\SubcpmkAsesmenPemetaanService;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SubcpmkResource extends Resource
{
    use HasKoordinatorMkScope;
    use HasMkTerpilihScope;
    use HasSemesterTerpilihFilter;

    protected static ?string $model = Subcpmk::class;

    protected static ?string $policy = SubcpmkPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Mata Kuliah');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('subcpmk', 3);
    }

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
     * Ringkasan taksonomi bloom (kognitif/afektif/psikomotorik) yang terisi, mis. ['C2', 'A2', 'P1'].
     *
     * @return list<string>
     */
    protected static function taksonomiBadges(Subcpmk $record): array
    {
        return array_values(array_filter([
            $record->bloom_kognitif,
            $record->bloom_afektif,
            $record->bloom_psikomotorik,
        ]));
    }

    /**
     * Rekap bobot evaluasi Sub-CPMK ini terhadap nilai akhir mata kuliah,
     * dikelompokkan per jenis evaluasi (mis. Tugas, Proyek Individu), agar
     * mudah dibaca berdekatan dengan kolom Bobot pada baris yang sama.
     * Bobot per jenis = bobot komponen penilaian × bobot pivot Sub-CPMK.
     */
    protected static function bobotEvaluasiHtml(Subcpmk $record): HtmlString
    {
        $perEvaluasi = SubcpmkAsesmenPemetaanService::rincianBobotEvaluasi($record->id);

        if ($perEvaluasi->isEmpty()) {
            return new HtmlString(
                '<p style="font-size:12px;opacity:.6;">Belum ada asesmen terpetakan ke Sub-CPMK ini.</p>',
            );
        }

        $service = app(RencanaEvaluasiService::class);

        $rincian = $perEvaluasi
            ->map(fn (float $bobot, string $nama): string => e($nama).' ('.$service->formatBobot($bobot).')')
            ->values()
            ->join(' · ');

        return new HtmlString(sprintf(
            '<p style="font-size:12px;"><strong>Bobot evaluasi: %s</strong> — %s</p>',
            $service->formatBobot((float) $perEvaluasi->sum()),
            $rincian,
        ));
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
        $mkTerpilih = MkTerpilih::currentId();

        if (filled($mkTerpilih)) {
            $mkIds = collect([$mkTerpilih]);
        }

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
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && app(SubcpmkPolicy::class)->viewAny($user)
            && (! PenawaranMkScope::isKoordinatorMkOnly($user) || MkTerpilih::current() !== null);
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
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(
                                'Bobot dihitung otomatis dari interaksi Sub-CPMK ini dengan Asesmen '
                                .'(komponen penilaian) — ubah lewat interaksi Sub-CPMK ↔ Asesmen pada '
                                .'halaman Komponen Penilaian, bukan di sini.',
                            ),

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
            ->description(fn (): HtmlString => MkTerpilih::bannerHtml())
            ->columns([
                Stack::make([
                    Split::make([
                        TextColumn::make('kode')
                            ->label('Kode')
                            ->searchable()
                            ->weight(FontWeight::Bold),

                        TextColumn::make('taksonomi')
                            ->label('Taksonomi')
                            ->getStateUsing(fn (Subcpmk $record): array => static::taksonomiBadges($record))
                            ->badge()
                            ->placeholder('—')
                            ->color('gray')
                            ->size('sm'),
                    ]),

                    TextColumn::make('cpmk.kode')
                        ->label('CPMK')
                        ->getStateUsing(fn (Subcpmk $record): string => $record->cpmk?->kode ?? '—')
                        ->size('sm')
                        ->color('gray'),

                    TextColumn::make('deskripsi')
                        ->label('Deskripsi')
                        ->searchable()
                        ->formatStateUsing(fn (?string $state): string => filled($state)
                            ? Str::limit(trim(strip_tags($state)), 100)
                            : '—')
                        ->size('sm')
                        ->color('gray'),

                    TextColumn::make('bobot')
                        ->label('Bobot Sub-CPMK')
                        ->suffix('%')
                        ->placeholder('—')
                        ->size('sm')
                        ->weight(FontWeight::Bold),

                    TextColumn::make('bobot_evaluasi')
                        ->label('')
                        ->html()
                        ->getStateUsing(fn (Subcpmk $record): HtmlString => static::bobotEvaluasiHtml($record)),
                ])->space(1),
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

                    return $query->whereHas(
                        'mkCpmk.cpmk',
                        fn (Builder $cpmkQuery): Builder => $cpmkQuery->where('mk_id', $mkId),
                    );
                }

                $semesterId = SemesterTerpilih::currentId($mkId);

                if (blank($mkId) || blank($semesterId)) {
                    return $query->whereRaw('1 = 0');
                }

                return $query
                    ->where('semester_id', $semesterId)
                    ->whereHas(
                        'mkCpmk.cpmk',
                        fn (Builder $cpmkQuery): Builder => $cpmkQuery->where('mk_id', $mkId),
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

    public static function getPages(): array
    {
        return [
            'index' => ListSubcpmks::route('/'),
            'create' => CreateSubcpmk::route('/create'),
            'edit' => EditSubcpmk::route('/{record}/edit'),
        ];
    }
}
