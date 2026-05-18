<?php

namespace App\Filament\Pages;

use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\Kalkulasi\Filament\Support\DashboardAcademicUnitOptions;
use App\Modules\AI\Filament\Widgets\AiInsightWidget;
use App\Modules\Kalkulasi\Filament\Widgets\CplPerMkUnitTable;
use App\Modules\Kalkulasi\Filament\Widgets\CplUnitChartWidget;
use App\Modules\Kalkulasi\Services\DashboardCplDataService;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;

class Dashboard extends BaseDashboard
{
    use CanAccessDashboardWidgets;
    use HasFiltersForm;

    public static function canAccess(): bool
    {
        return static::canViewDashboardWidgets();
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        if (! static::canViewDashboardWidgets()) {
            return [
                AccountWidget::class,
            ];
        }

        return [
            AiInsightWidget::class,
            CplUnitChartWidget::class,
            CplPerMkUnitTable::class,
            AccountWidget::class,
        ];
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
                    ->columns(3),
            ]);
    }
}
