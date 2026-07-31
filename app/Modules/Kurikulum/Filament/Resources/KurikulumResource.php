<?php

namespace App\Modules\Kurikulum\Filament\Resources;

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\CreateKurikulum;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\EditKurikulum;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\ListKurikulums;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\States\AktifState;
use App\Modules\Kurikulum\States\BokState;
use App\Modules\Kurikulum\States\CplState;
use App\Modules\Kurikulum\States\DraftState;
use App\Modules\Kurikulum\States\KurikulumState;
use App\Modules\Kurikulum\States\MkState;
use App\Modules\Kurikulum\States\ProfilLulusanState;
use App\Modules\Kurikulum\States\SetdosenmkState;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Mk;
use App\Support\Filament\DelegasiMenu;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Slider;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class KurikulumResource extends Resource
{
    protected static ?string $model = Kurikulum::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'Kurikulum';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'kurikulum';

    protected static ?string $pluralModelLabel = 'kurikulum';

    protected static ?string $recordTitleAttribute = 'nama';

    protected static ?string $slug = 'kurikulums';

    public static function shouldRegisterNavigation(): bool
    {
        if (DelegasiMenu::sembunyikanDariSuperAdmin()) {
            return false;
        }

        return static::canAccess();
    }

    /**
     * @var array<string, class-string<KurikulumState>>
     */
    public static array $stateClassMap = [
        'draft' => DraftState::class,
        'profil_lulusan' => ProfilLulusanState::class,
        'cpl' => CplState::class,
        'bok' => BokState::class,
        'mk' => MkState::class,
        'setdosenmk' => SetdosenmkState::class,
        'aktif' => AktifState::class,
    ];

    /**
     * @return array<string, string>
     */
    public static function stateOptions(): array
    {
        return [
            'draft' => 'Draft',
            'profil_lulusan' => 'Profil Lulusan',
            'cpl' => 'CPL',
            'bok' => 'BoK',
            'mk' => 'Mata Kuliah',
            'setdosenmk' => 'Set Dosen MK',
            'aktif' => 'Aktif',
        ];
    }

    public static function stateColor(string $state): string
    {
        return match ($state) {
            'draft' => 'gray',
            'profil_lulusan' => 'info',
            'cpl' => 'warning',
            'bok' => 'primary',
            'mk' => 'info',
            'setdosenmk' => 'warning',
            'aktif' => 'success',
            default => 'gray',
        };
    }

    /**
     * @return class-string<KurikulumState>
     */
    public static function stateClassFor(string $stateName): string
    {
        return static::$stateClassMap[$stateName] ?? DraftState::class;
    }

    /**
     * @return list<string>
     */
    public static function workflowStepsFor(Kurikulum $kurikulum): array
    {
        $kurikulum->loadMissing('academicUnit');

        if ($kurikulum->academicUnit->isProdi()) {
            return ['draft', 'profil_lulusan', 'cpl', 'bok', 'mk', 'setdosenmk', 'aktif'];
        }

        return ['draft', 'cpl', 'bok', 'mk', 'setdosenmk', 'aktif'];
    }

    /**
     * @return Collection<int, string>
     */
    public static function scopedKurikulumUnitIds(): Collection
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return collect();
        }

        return AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('academicUnit');

        $unitIds = static::scopedKurikulumUnitIds();

        if ($unitIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        if (Auth::user()?->hasRole('Super Admin')) {
            return $query;
        }

        return $query->whereIn('academic_unit_id', $unitIds);
    }

    public static function form(Schema $schema): Schema
    {
        $scopedUnitIds = static::scopedKurikulumUnitIds();

        return $schema
            ->components([
                Section::make('Data Kurikulum')
                    ->schema([
                        Select::make('academic_unit_id')
                            ->label('Unit akademik')
                            ->options(function () use ($scopedUnitIds): array {
                                return AcademicUnit::query()
                                    ->whereIn('id', $scopedUnitIds)
                                    ->orderBy('nama')
                                    ->get()
                                    ->mapWithKeys(fn (AcademicUnit $unit): array => [
                                        $unit->id => AcademicUnitResource::formatUnitOptionLabel($unit),
                                    ])
                                    ->all();
                            })
                            ->searchable()
                            ->required()
                            ->default($scopedUnitIds->count() === 1 ? $scopedUnitIds->first() : null)
                            ->disabled($scopedUnitIds->count() === 1)
                            ->dehydrated()
                            ->columnSpanFull(),

                        TextInput::make('nama')
                            ->label('Nama kurikulum')
                            ->required()
                            ->maxLength(150)
                            ->columnSpanFull(),

                        TextInput::make('kode')
                            ->label('Kode')
                            ->maxLength(30)
                            ->placeholder('KUR-2025-IF'),

                        Select::make('tahun')
                            ->label('Tahun')
                            ->options(fn (): array => collect(range((int) date('Y') + 1, 2015))
                                ->mapWithKeys(fn (int $year): array => [$year => (string) $year])
                                ->all())
                            ->default((int) date('Y'))
                            ->required(),

                        Placeholder::make('target_capaian_label')
                            ->hiddenLabel()
                            ->content(new HtmlString(
                                '<p class="text-sm font-medium leading-6 text-gray-950 dark:text-white">'
                                .'Target capaian lulusan ('
                                .'<span x-text="Math.round($wire.data?.target_capaian_lulusan ?? 75)"></span>%)'
                                .'</p>'
                            ))
                            ->columnSpanFull(),

                        Slider::make('target_capaian_lulusan')
                            ->hiddenLabel()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(1)
                            ->default(75)
                            ->required()
                            ->tooltips(true)
                            ->partiallyRenderAfterStateUpdated(false)
                            ->columnSpanFull(),

                        RichEditor::make('deskripsi')
                            ->label('Deskripsi')
                            ->columnSpanFull(),

                        Toggle::make('is_active')
                            ->label('Kurikulum aktif')
                            ->inline(false)
                            ->default(false),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    TextColumn::make('kode')
                        ->label('Kode')
                        ->searchable()
                        ->sortable()
                        ->hidden(),

                    TextColumn::make('tahun')
                        ->label('Tahun')
                        ->sortable()
                        ->hidden(),

                    TextColumn::make('header_card')
                        ->label('')
                        ->state(fn (Kurikulum $record): string => static::headerCardHtml($record)->toHtml())
                        ->html(),

                    TextColumn::make('nama')
                        ->label('Nama')
                        ->searchable()
                        ->sortable()
                        ->weight(FontWeight::Medium),

                    TextColumn::make('unit_dan_menu')
                        ->label('')
                        ->state(fn (Kurikulum $record): string => static::unitDanMenuHtml($record)->toHtml())
                        ->html(),
                ])->space(1),
            ])
            ->contentGrid([
                'md' => 2,
                'xl' => 3,
            ])
            ->paginated([6, 12, 24])
            ->defaultPaginationPageOption(12)
            ->selectable(false)
            ->recordUrl(null)
            ->recordAction('kerjakan')
            ->recordClasses(fn (Kurikulum $record): array => static::isKurikulumSedangDigunakan($record)
                ? ['silogy-kurikulum-card-sedang-dikerjakan']
                : ['silogy-kurikulum-card-belum-dikerjakan'])
            ->filters([
                SelectFilter::make('academic_unit_id')
                    ->label('Unit akademik')
                    ->options(fn (): array => AcademicUnit::query()
                        ->whereIn('id', static::scopedKurikulumUnitIds())
                        ->orderBy('nama')
                        ->get()
                        ->mapWithKeys(fn (AcademicUnit $unit): array => [$unit->id => $unit->nama_lengkap])
                        ->all()),

                SelectFilter::make('academic_unit_type')
                    ->label('Jenis unit')
                    ->options(AcademicUnitResource::typeOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $type = $data['value'] ?? null;

                        if (blank($type)) {
                            return $query;
                        }

                        return $query->whereHas(
                            'academicUnit',
                            fn (Builder $unitQuery): Builder => $unitQuery->where('type', $type),
                        );
                    }),

                SelectFilter::make('state')
                    ->label('State workflow')
                    ->options(static::stateOptions()),
            ])
            ->recordActions([
                Action::make('kerjakan')
                    ->label(fn (Kurikulum $record): string => static::isKurikulumSedangDigunakan($record)
                        ? 'Sedang dikerjakan'
                        : 'Kerjakan')
                    ->icon(fn (Kurikulum $record): Heroicon => static::isKurikulumSedangDigunakan($record)
                        ? Heroicon::OutlinedCheckBadge
                        : Heroicon::OutlinedPlayCircle)
                    ->color(fn (Kurikulum $record): string => static::isKurikulumSedangDigunakan($record)
                        ? 'gray'
                        : 'success')
                    ->disabled(fn (Kurikulum $record): bool => static::isKurikulumSedangDigunakan($record))
                    ->extraAttributes(fn (Kurikulum $record): array => [
                        'class' => static::isKurikulumSedangDigunakan($record)
                            ? 'silogy-kurikulum-aksi-sedang-dikerjakan'
                            : 'silogy-kurikulum-aksi-kerjakan',
                    ])
                    ->action(function (Kurikulum $record): void {
                        KurikulumTerpilih::set($record->id);

                        Notification::make()
                            ->title('Kurikulum sedang dikerjakan diganti')
                            ->body($record->nama)
                            ->success()
                            ->send();
                    }),
                EditAction::make()
                    ->label('')
                    ->iconButton()
                    ->tooltip('Ubah')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->extraAttributes([
                        'class' => 'silogy-kurikulum-edit-action',
                        'style' => 'margin-inline-start:auto;',
                    ]),
            ])
            ->extraAttributes([
                'class' => 'silogy-kurikulum-cards',
            ]);
    }

    public static function headerCardHtml(Kurikulum $kurikulum): HtmlString
    {
        $statusLabel = $kurikulum->is_active ? 'Aktif' : 'Nonaktif';
        $statusBg = $kurikulum->is_active ? '#dcfce7' : '#f3f4f6';
        $statusColor = $kurikulum->is_active ? '#166534' : '#6b7280';
        $statusBorder = $kurikulum->is_active ? '#86efac' : '#d1d5db';
        $kode = filled($kurikulum->kode) ? $kurikulum->kode : '—';

        return new HtmlString(
            '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;">'
            .'<div style="min-width:0;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;">'
            .'<span style="font-weight:700;font-size:14px;color:inherit;">'.e($kode).'</span>'
            .'<span style="font-size:13px;opacity:.75;">'.e((string) $kurikulum->tahun).'</span>'
            .'</div>'
            .'<span style="flex-shrink:0;display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;'
            .'font-size:11px;font-weight:600;line-height:1.4;background:'.$statusBg.';color:'.$statusColor.';'
            .'border:1px solid '.$statusBorder.';">'.e($statusLabel).'</span>'
            .'</div>'
        );
    }

    public static function isKurikulumSedangDigunakan(Kurikulum $kurikulum): bool
    {
        return KurikulumTerpilih::currentId() === $kurikulum->id;
    }

    /**
     * @return array<string, bool>
     */
    public static function ketersediaanMenu(Kurikulum $kurikulum): array
    {
        $kurikulum->loadMissing('academicUnit');

        $menu = [];

        if ($kurikulum->academicUnit?->isProdi()) {
            $menu['profil'] = ProfilLulusan::query()
                ->where('kurikulum_id', $kurikulum->id)
                ->exists();
        }

        $menu['cpl'] = Cpl::query()->where('kurikulum_id', $kurikulum->id)->exists();
        $menu['bok'] = Bok::query()->where('kurikulum_id', $kurikulum->id)->exists();
        $menu['mk'] = Mk::query()->where('kurikulum_id', $kurikulum->id)->exists();

        return $menu;
    }

    /**
     * Label unit (jenis + nama) dan badge menu, satu blok agar kiri sejajar.
     */
    public static function unitDanMenuHtml(Kurikulum $kurikulum): HtmlString
    {
        $kurikulum->loadMissing('academicUnit');
        [$typeLabel, $unitNama] = AcademicUnitResource::jenisDanNamaUntukCard($kurikulum->academicUnit);

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;align-items:stretch;gap:6px;width:100%;margin:0;padding:0;">'
            .'<div style="font-size:12px;line-height:1.4;color:#6b7280;margin:0;padding:0;">'
            .'<span style="font-weight:600;color:#374151;">'.e($typeLabel).'</span>'
            .' · '.e($unitNama)
            .'</div>'
            .static::ketersediaanMenuHtml($kurikulum)->toHtml()
            .'</div>'
        );
    }

    /**
     * Badge menu Profil/CPL/BoK/MK yang sekaligus menjadi tautan ke halaman terkait.
     */
    public static function ketersediaanMenuHtml(Kurikulum $kurikulum): HtmlString
    {
        $labels = [
            'profil' => 'Profil',
            'cpl' => 'CPL',
            'bok' => 'BoK',
            'mk' => 'MK',
        ];

        $badges = collect(static::ketersediaanMenu($kurikulum))
            ->map(function (bool $ada, string $menu) use ($kurikulum, $labels): string {
                $label = $labels[$menu] ?? strtoupper($menu);
                $status = $ada ? 'Ada' : 'Belum';
                $url = route('silogy.kurikulum-navigasi', [
                    'kurikulum' => $kurikulum->id,
                    'menu' => $menu,
                ]);

                $background = $ada ? '#dcfce7' : '#f3f4f6';
                $color = $ada ? '#166534' : '#6b7280';
                $border = $ada ? '#86efac' : '#d1d5db';

                return '<a href="'.e($url).'" '
                    .'onclick="event.stopPropagation()" '
                    .'style="display:inline-flex;align-items:center;padding:3px 8px;'
                    .'border-radius:6px;font-size:11px;font-weight:600;line-height:1.4;text-decoration:none;'
                    .'background:'.$background.';color:'.$color.';border:1px solid '.$border.';">'
                    .e($label).' · '.e($status)
                    .'</a>';
            })
            ->implode('');

        return new HtmlString(
            '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin:0;padding:0;">'
            .$badges
            .'</div>'
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListKurikulums::route('/'),
            'create' => CreateKurikulum::route('/create'),
            'edit' => EditKurikulum::route('/{record}/edit'),
        ];
    }
}
