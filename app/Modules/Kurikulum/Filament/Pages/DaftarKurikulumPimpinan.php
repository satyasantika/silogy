<?php

namespace App\Modules\Kurikulum\Filament\Pages;

use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Services\DashboardPimpinanService;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\Concerns\ForcesFullPageRender;
use App\Support\Filament\DelegasiMenu;
use App\Support\Filament\NavigationGroupPeran;
use App\Support\Filament\NavigationSortPeran;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Daftar card kurikulum untuk Pimpinan — tampilan sama dengan /kurikulums,
 * bedanya badge mengarah ke menu laporan (Hasil / Grafik / Per Mahasiswa)
 * dan tidak ada aksi edit/buat.
 */
class DaftarKurikulumPimpinan extends Page implements HasActions, HasTable
{
    use ForcesFullPageRender;
    use InteractsWithActions;
    use InteractsWithTable;

    protected string $view = 'filament.modules.kurikulum.pages.daftar-kurikulum-pimpinan';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Laporan');
    }

    public static function getNavigationSort(): ?int
    {
        return NavigationSortPeran::resolve('daftar-kurikulum', 5);
    }

    protected static ?string $navigationLabel = 'Kurikulum';

    protected static ?string $title = 'Kurikulum';

    protected static ?string $slug = 'laporan/kurikulums';

    public static function canAccess(): bool
    {
        if (! DelegasiMenu::peranAktifPimpinan()) {
            return false;
        }

        $user = auth()->user();

        return $user instanceof User && $user->can('lihat_laporan');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /**
     * @return Collection<int, string>
     */
    public static function scopedUnitIds(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return AcademicUnitScope::scopedPimpinanUnitIdsWithDescendantsFor($user);
    }

    public function table(Table $table): Table
    {
        $unitIds = static::scopedUnitIds();

        return $table
            ->query(
                $unitIds->isEmpty()
                    ? Kurikulum::query()->whereRaw('1 = 0')
                    : Kurikulum::query()->with('academicUnit')->whereIn('academic_unit_id', $unitIds),
            )
            ->columns([
                Stack::make([
                    TextColumn::make('kode')
                        ->label('Kode')
                        ->searchable()
                        ->hidden(),

                    TextColumn::make('tahun')
                        ->label('Tahun')
                        ->hidden(),

                    TextColumn::make('header_card')
                        ->label('')
                        ->state(fn (Kurikulum $record): string => static::headerCardHtml($record)->toHtml())
                        ->html(),

                    TextColumn::make('nama')
                        ->label('Nama')
                        ->searchable()
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
                        ->whereIn('id', static::scopedUnitIds())
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
            ])
            ->extraAttributes([
                'class' => 'silogy-kurikulum-cards',
            ]);
    }

    public static function isKurikulumSedangDigunakan(Kurikulum $kurikulum): bool
    {
        return KurikulumTerpilih::currentId() === $kurikulum->id;
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
            .static::kpiProgressPenilaianHtml($kurikulum)->toHtml()
            .'</div>'
        );
    }

    /**
     * Badge laporan Pimpinan (bukan Profil/CPL/BoK/MK).
     *
     * @return array<string, bool>
     */
    public static function ketersediaanMenu(Kurikulum $kurikulum): array
    {
        $adaCpl = Cpl::query()->where('kurikulum_id', $kurikulum->id)->exists();

        return [
            'hasil' => $adaCpl,
            'grafik' => $adaCpl,
            'mahasiswa' => $adaCpl,
        ];
    }

    public static function ketersediaanMenuHtml(Kurikulum $kurikulum): HtmlString
    {
        $labels = [
            'hasil' => 'Hasil CPL',
            'grafik' => 'Grafik CPL',
            'mahasiswa' => 'Per Mahasiswa',
        ];

        $icons = [
            'hasil' => 'heroicon-m-clipboard-document-check',
            'grafik' => 'heroicon-m-chart-bar',
            'mahasiswa' => 'heroicon-m-user-group',
        ];

        $badges = collect(static::ketersediaanMenu($kurikulum))
            ->map(function (bool $ada, string $menu) use ($kurikulum, $labels, $icons): string {
                $label = $labels[$menu] ?? strtoupper($menu);
                $url = route('silogy.kurikulum-navigasi-pimpinan', [
                    'kurikulum' => $kurikulum->id,
                    'menu' => $menu,
                ]);

                $background = $ada ? '#dcfce7' : '#f3f4f6';
                $color = $ada ? '#166534' : '#6b7280';
                $border = $ada ? '#86efac' : '#d1d5db';
                $title = $ada
                    ? $label.' — data tersedia'
                    : $label.' — belum ada CPL pada kurikulum ini';

                $iconName = $icons[$menu] ?? 'heroicon-m-link';
                $iconHtml = svg($iconName, [
                    'style' => 'width:12px;height:12px;flex-shrink:0;',
                    'aria-hidden' => 'true',
                ])->toHtml();

                return '<a href="'.e($url).'" '
                    .'class="silogy-menu-badge silogy-menu-badge--'.e($menu).'" '
                    .'onclick="event.stopPropagation()" '
                    .'title="'.e($title).'" '
                    .'aria-label="'.e($title).'" '
                    .'style="display:inline-flex;align-items:center;gap:5px;padding:3px 8px;'
                    .'border-radius:6px;font-size:11px;font-weight:600;line-height:1.4;text-decoration:none;'
                    .'background:'.$background.';color:'.$color.';border:1px solid '.$border.';">'
                    .$iconHtml
                    .'<span>'.e($label).'</span>'
                    .'</a>';
            })
            ->implode('');

        return new HtmlString(
            '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin:0;padding:0;">'
            .$badges
            .'</div>'
        );
    }

    /**
     * KPI bento donat per card — scope penawaran mengikuti
     * AnalisisMkProdiService::mkUnitIdsUntukKurikulum (prodi vs rollup).
     */
    public static function kpiProgressPenilaianHtml(Kurikulum $kurikulum): HtmlString
    {
        return new HtmlString(view(
            'filament.modules.kurikulum.partials.laporan-kurikulum-kpi',
            [
                ...app(DashboardPimpinanService::class)
                    ->rekapProgressPenilaianUntukKurikulum($kurikulum),
                'compact' => true,
                'page' => false,
                'tampil_mk' => true,
                'tampil_mahasiswa' => true,
            ],
        )->render());
    }
}
