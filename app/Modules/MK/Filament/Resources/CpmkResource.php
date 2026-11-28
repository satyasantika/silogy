<?php

namespace App\Modules\MK\Filament\Resources;

use App\Models\User;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\CreateCpmk;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\EditCpmk;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\ListCpmks;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Filament\Support\Concerns\HasMkTerpilihScope;
use App\Modules\MK\Filament\Support\Concerns\HasSemesterTerpilihFilter;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Policies\CpmkPolicy;
use App\Modules\MK\Services\CpmkCplPemetaanService;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\MK\Support\PenawaranMkScope;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class CpmkResource extends Resource
{
    use HasKoordinatorMkScope;
    use HasMkTerpilihScope;
    use HasSemesterTerpilihFilter;

    protected static ?string $model = Cpmk::class;

    protected static ?string $policy = CpmkPolicy::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Mata Kuliah');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('cpmk', 2);
    }

    protected static ?string $modelLabel = 'CPMK';

    protected static ?string $pluralModelLabel = 'CPMK';

    protected static ?string $slug = 'cpmk';

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin() || DelegasiMenu::peranAktifDosenBukanAdmin()) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && app(CpmkPolicy::class)->viewAny($user)
            && (! PenawaranMkScope::isKoordinatorMkOnly($user) || MkTerpilih::current() !== null);
    }

    public static function canAccess(): bool
    {
        if (DelegasiMenu::peranAktifDosenBukanAdmin()) {
            return false;
        }

        $user = Auth::user();

        return $user instanceof User && app(CpmkPolicy::class)->viewAny($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with('mk')
            ->withCount('mkCpmks');

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

        return $query->whereIn('mk_id', $mkIds);
    }

    public static function form(Schema $schema): Schema
    {
        $mkTerpilih = MkTerpilih::currentId();
        $mkOptions = static::scopedKoordinatorMkOptions();

        if (filled($mkTerpilih) && array_key_exists($mkTerpilih, $mkOptions)) {
            $mkOptions = array_intersect_key($mkOptions, [$mkTerpilih => true]);
        }

        return $schema
            ->components([
                Placeholder::make('peringatan_sudah_dinilai')
                    ->hiddenLabel()
                    ->content(new HtmlString('<p style="font-size:12px;"><strong>CPMK ini sudah dinilai.</strong> Mengubah kode atau deskripsi hanya memperbarui label; nilai mahasiswa tidak berubah. Perubahan tercatat di audit log.</p>'))
                    ->visible(fn (?Cpmk $record): bool => $record !== null && $record->sudahDinilai())
                    ->columnSpanFull(),
                Section::make('CPMK')
                    ->schema([
                        Select::make('mk_id')
                            ->label('Mata kuliah')
                            ->options($mkOptions)
                            ->searchable()
                            ->required()
                            ->default($mkTerpilih ?? (count($mkOptions) === 1 ? array_key_first($mkOptions) : null))
                            ->disabled(filled($mkTerpilih) || count($mkOptions) === 1)
                            ->dehydrated()
                            ->live(),

                        Select::make('kode_cpl_terpetakan')
                            ->label('CPL terpetakan')
                            ->helperText('Opsional; pilih lebih dari satu bila CPMK ini berinteraksi dengan beberapa CPL. Sama dengan kolom "kode CPL terpetakan" pada impor massal. Tanpa pemetaan CPL, CPMK ini belum bisa dipilih saat membuat Sub-CPMK. CPL yang dipakai Sub-CPMK dikunci sampai Sub-CPMK-nya dihapus.')
                            ->multiple()
                            ->options(fn (Get $get): array => static::kodeCplOptions($get('mk_id')))
                            ->searchable()
                            // CPL yang dipakai Sub-CPMK dikunci (nonaktif dan tak bisa dilepas);
                            // dihitung ulang tiap render sehingga terbuka lagi bila Sub-CPMK-nya hilang.
                            ->disableOptionWhen(fn (string $value, ?Cpmk $record): bool => $record instanceof Cpmk
                                && in_array($value, CpmkCplPemetaanService::kodeCplTerkunci($record), true))
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set, ?Cpmk $record): void {
                                if (! $record instanceof Cpmk) {
                                    return;
                                }

                                $set('kode_cpl_terpetakan', array_values(array_unique([
                                    ...(array) $state,
                                    ...CpmkCplPemetaanService::kodeCplTerkunci($record),
                                ])));
                            })
                            ->columnSpanFull(),

                        TextInput::make('kode')
                            ->label('Kode')
                            ->required()
                            ->maxLength(15),

                        Textarea::make('deskripsi')
                            ->label('Deskripsi')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Kode CPL yang sudah punya interaksi CPL–MK pada MK ini, kunci dan nilainya
     * kode CPL (bentuk yang dipakai CpmkCplPemetaanService).
     *
     * @return array<string, string>
     */
    public static function kodeCplOptions(?string $mkId): array
    {
        if (blank($mkId)) {
            return [];
        }

        return CplMk::query()
            ->where('mk_id', $mkId)
            ->with('cplBok.cpl')
            ->get()
            ->map(fn (CplMk $cplMk): ?string => $cplMk->cplBok?->cpl?->kode)
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $kode): array => [$kode => $kode])
            ->all();
    }

    public static function table(Table $table): Table
    {
        // CPMK kini berlaku per semester lewat cpmk_semester. Daftar untuk
        // Koordinator MK disaring ke semester terpilih supaya pertanyaan
        // "pakai CPMK lama atau susun baru?" punya jawaban yang jelas; peran
        // lain (Admin/Tim Kurikulum/Auditor) tetap melihat lintas semester
        // karena mereka butuh pandangan menyeluruh.
        $table = static::applySemesterTerpilihTable(
            $table,
            fn (Builder $query, string $semesterId): Builder => Cpmk::saringSemester($query, $semesterId),
        );

        return static::applyMkTerpilihCardTable(
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
                        ->weight(FontWeight::Bold),

                    TextColumn::make('mkCpmks_count')
                        ->label('CPL')
                        ->badge()
                        ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray'),
                ]),

                TextColumn::make('deskripsi')
                    ->label('Deskripsi')
                    ->searchable()
                    ->formatStateUsing(fn (?string $state): string => filled($state)
                        ? Str::limit(trim(strip_tags($state)), 120)
                        : '—')
                    ->size('sm')
                    ->color('gray'),

                TextColumn::make('semesters.kode')
                    ->label('Berlaku pada semester')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—')
                    ->size('sm')
                    // Hanya perlu saat daftar tidak disaring per semester —
                    // bagi Koordinator MK kolom ini cuma mengulang filternya.
                    ->visible(fn (): bool => ! SemesterTerpilih::berlakuUntukUser()),
            ],
            fn (Builder $query, string $mkId): Builder => $query->where('mk_id', $mkId),
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCpmks::route('/'),
            'create' => CreateCpmk::route('/create'),
            'edit' => EditCpmk::route('/{record}/edit'),
        ];
    }
}
