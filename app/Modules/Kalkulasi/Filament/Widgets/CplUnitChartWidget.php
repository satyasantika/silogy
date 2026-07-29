<?php

namespace App\Modules\Kalkulasi\Filament\Widgets;

use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\Kalkulasi\Services\DashboardCplDataService;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Contracts\Support\Htmlable;

class CplUnitChartWidget extends ChartWidget
{
    use CanAccessDashboardWidgets;
    use InteractsWithPageFilters;

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected ?string $heading = 'Capaian CPL per Unit';

    protected ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::canViewDashboardCplWidgets();
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->heading;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $academicUnitId = $this->pageFilters['academic_unit_id'] ?? null;
        $semesterId = $this->pageFilters['semester_id'] ?? null;

        if ($academicUnitId === null || $semesterId === null) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $payload = app(DashboardCplDataService::class)->chartData(
            (string) $academicUnitId,
            (string) $semesterId,
        );

        $labels = [];
        $rataRata = [];
        $targetLine = [];

        foreach ($payload['rows'] as $baris) {
            $labels[] = $baris['kode'];
            $rataRata[] = $baris['rata_rata'] ?? 0;
            $targetLine[] = $payload['target'];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Rata-rata capaian',
                    'data' => $rataRata,
                    'type' => 'bar',
                ],
                [
                    'label' => 'Target kurikulum',
                    'data' => $targetLine,
                    'type' => 'line',
                    'borderColor' => '#ef4444',
                    'backgroundColor' => 'transparent',
                    'pointRadius' => 0,
                    'borderWidth' => 2,
                    'fill' => false,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getOptions(): ?array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'max' => 100,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
        ];
    }
}
