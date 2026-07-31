<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AktivitasTerbaruWidget;
use App\Filament\Widgets\SuperAdminKpiWidget;
use App\Filament\Widgets\WelcomeWidget;
use App\Modules\AI\Filament\Widgets\AiInsightWidget;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\Kalkulasi\Filament\Support\DashboardAcademicUnitOptions;
use App\Modules\Kalkulasi\Filament\Widgets\CplPerMkUnitTable;
use App\Modules\Kalkulasi\Filament\Widgets\CplUnitChartWidget;
use App\Modules\Kalkulasi\Services\DashboardCplDataService;
use App\Modules\Kurikulum\Filament\Widgets\CplTertinggiChartWidget;
use App\Modules\Kurikulum\Filament\Widgets\KurikulumKpiWidget;
use App\Modules\Kurikulum\Filament\Widgets\KurikulumTerpilihWidget;
use App\Modules\Kurikulum\Filament\Widgets\MkCapaianTertinggiTable;
use App\Modules\MK\Filament\Widgets\KoordinatorMkAksesWidget;
use App\Modules\MK\Filament\Widgets\KoordinatorMkCapaianTertinggiTable;
use App\Modules\MK\Filament\Widgets\KoordinatorMkCplTertinggiChartWidget;
use App\Modules\MK\Filament\Widgets\KoordinatorMkKpiWidget;
use App\Modules\Penilaian\Filament\Widgets\DosenPengampuCapaianTertinggiTable;
use App\Modules\Penilaian\Filament\Widgets\DosenPengampuCplTertinggiChartWidget;
use App\Modules\Penilaian\Filament\Widgets\DosenPengampuKpiWidget;
use App\Modules\Penilaian\Filament\Widgets\RekapMkDosenWidget;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    use CanAccessDashboardWidgets;
    use HasFiltersForm;

    /** Beranda aplikasi ada di `/` — dashboard panel di slug terpisah agar tidak bentrok dengan landing. */
    protected static string $routePath = 'dashboard';

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        if (! static::canViewDashboardWidgets()) {
            return [];
        }

        // Tim Kurikulum bekerja per kurikulum, bukan per unit+semester:
        // KPI kurikulum + peringkat capaian lintas kurikulum menggantikan
        // widget CPL ber-filter (lihat isDashboardTimKurikulum()).
        if (static::isDashboardTimKurikulum()) {
            return [
                KurikulumKpiWidget::class,
                CplTertinggiChartWidget::class,
                MkCapaianTertinggiTable::class,
            ];
        }

        // Koordinator MK bekerja per MK yang dikoordinasikan: KPI MK +
        // peringkat capaian discope ke MK itu, menggantikan widget CPL
        // ber-filter (lihat isDashboardKoordinatorMk()).
        if (static::isDashboardKoordinatorMk()) {
            return [
                KoordinatorMkKpiWidget::class,
                KoordinatorMkCplTertinggiChartWidget::class,
                KoordinatorMkCapaianTertinggiTable::class,
            ];
        }

        // Dosen Pengampu bekerja per kelas yang diampu: KPI MK + peringkat
        // capaian discope ke kelas yang diajar (lihat isDashboardDosenPengampu()).
        if (static::isDashboardDosenPengampu()) {
            return [
                DosenPengampuKpiWidget::class,
                DosenPengampuCplTertinggiChartWidget::class,
                DosenPengampuCapaianTertinggiTable::class,
            ];
        }

        return [
            AiInsightWidget::class,
            CplUnitChartWidget::class,
            CplPerMkUnitTable::class,
        ];
    }

    /**
     * Ucapan selamat datang (WelcomeWidget) selalu paling atas setelah judul,
     * sebelum filter dashboard.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->schema(fn (): array => $this->getWidgetsSchemaComponents(array_values(array_filter([
                        WelcomeWidget::class,
                        SuperAdminKpiWidget::class,
                        AktivitasTerbaruWidget::class,
                        // Kurikulum yang dikerjakan cukup diwakili KPI
                        // KurikulumKpiWidget pada mode Tim Kurikulum.
                        static::isDashboardTimKurikulum() ? null : KurikulumTerpilihWidget::class,
                        // Jalan pintas lama digantikan KPI widget pada mode
                        // dashboard barunya masing-masing.
                        static::isDashboardKoordinatorMk() ? null : KoordinatorMkAksesWidget::class,
                        static::isDashboardDosenPengampu() ? null : RekapMkDosenWidget::class,
                    ])))),
                ...(static::canViewDashboardCplWidgets() ? [$this->getFiltersFormContentComponent()] : []),
                $this->getWidgetsContentComponent(),
            ]);
    }

    public function mountHasFilters(): void
    {
        $shouldPersistFiltersInSession = $this->persistsFiltersInSession();
        $filtersSessionKey = $this->getFiltersSessionKey();

        if (! count($this->filters ?? [])) {
            $this->filters = null;
        }

        if (
            ($this->filters === null) &&
            $shouldPersistFiltersInSession &&
            session()->has($filtersSessionKey)
        ) {
            $this->filters = session()->get($filtersSessionKey);
        }

        if ($this->filters === null || ! count($this->filters)) {
            $this->filters = $this->buildDefaultFilters();
        }

        if ($this->filters) {
            $this->normalizeTableFilterValuesFromQueryString($this->filters);
        }

        if (method_exists($this, 'getFiltersForm')) {
            $this->getFiltersForm()->fill($this->filters);
        }

        if ($shouldPersistFiltersInSession) {
            session()->put($filtersSessionKey, $this->filters);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildDefaultFilters(): array
    {
        // Tanpa form filter (mis. mode Tim Kurikulum) tidak perlu menyiapkan
        // opsi unit/semester/CPL yang tidak pernah dirender.
        if (! static::canViewDashboardCplWidgets()) {
            return [];
        }

        $unitOptions = DashboardAcademicUnitOptions::forUser();
        $semester = Semester::query()->where('status_aktif', true)->first();

        $academicUnitId = array_key_first($unitOptions);
        $semesterId = $semester?->id;
        $cplId = null;

        if ($academicUnitId !== null && $semesterId !== null) {
            $cplOptions = app(DashboardCplDataService::class)->cplOptionsForUnit(
                (string) $academicUnitId,
                (string) $semesterId,
            );
            $cplId = array_key_first($cplOptions);
        }

        return [
            'academic_unit_id' => $academicUnitId,
            'semester_id' => $semesterId,
            'cpl_id' => $cplId,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter Dashboard CPL')
                    ->description('Pilih unit, semester, dan CPL untuk data pada seluruh widget di bawah.')
                    ->icon(Heroicon::OutlinedFunnel)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('academic_unit_id')
                            ->label('Unit Akademik')
                            ->options(fn (): array => DashboardAcademicUnitOptions::forUser())
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, callable $set): void {
                                $unitId = $get('academic_unit_id');
                                $semesterId = $get('semester_id');

                                if ($unitId === null || $semesterId === null) {
                                    $set('cpl_id', null);

                                    return;
                                }

                                $cplOptions = app(DashboardCplDataService::class)->cplOptionsForUnit(
                                    (string) $unitId,
                                    (string) $semesterId,
                                );

                                $set('cpl_id', array_key_first($cplOptions));
                            }),
                        Select::make('semester_id')
                            ->label('Semester')
                            ->options(
                                fn (): array => Semester::query()
                                    ->orderByDesc('tahun_mulai')
                                    ->orderByDesc('jenis')
                                    ->pluck('nama', 'id')
                                    ->all(),
                            )
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, callable $set): void {
                                $unitId = $get('academic_unit_id');
                                $semesterId = $get('semester_id');

                                if ($unitId === null || $semesterId === null) {
                                    $set('cpl_id', null);

                                    return;
                                }

                                $cplOptions = app(DashboardCplDataService::class)->cplOptionsForUnit(
                                    (string) $unitId,
                                    (string) $semesterId,
                                );

                                $set('cpl_id', array_key_first($cplOptions));
                            }),
                        Select::make('cpl_id')
                            ->label('CPL (drill-down)')
                            ->options(function (Get $get): array {
                                $unitId = $get('academic_unit_id');
                                $semesterId = $get('semester_id');

                                if ($unitId === null || $semesterId === null) {
                                    return [];
                                }

                                return app(DashboardCplDataService::class)->cplOptionsForUnit(
                                    (string) $unitId,
                                    (string) $semesterId,
                                );
                            })
                            ->required(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ]),
            ]);
    }
}
