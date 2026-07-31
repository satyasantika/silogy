<?php

namespace App\Modules\Penilaian\Filament\Widgets;

use App\Models\User;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\Penilaian\Services\DashboardDosenPengampuService;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Capaian CPL tertinggi pada kelas yang diampu user, lintas kurikulum dan
 * semester — analog CplTertinggiChartWidget milik Tim Kurikulum, tapi
 * discope ke kelas yang benar-benar diajar (dosen_pengampu_id).
 */
class DosenPengampuCplTertinggiChartWidget extends ChartWidget
{
    use CanAccessDashboardWidgets;

    public const JUMLAH_CPL = 5;

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Capaian CPL Tertinggi (MK yang diampu)';

    protected ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::canViewDashboardWidgets()
            && static::isDashboardDosenPengampu();
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->heading;
    }

    public function getDescription(): string|Htmlable|null
    {
        return $this->barisCplTertinggi() === []
            ? 'Belum ada hasil kalkulasi CPL yang bisa diperingkat.'
            : sprintf('%d CPL dengan rerata capaian tertinggi dari kelas yang Anda ampu.', self::JUMLAH_CPL);
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = $this->barisCplTertinggi();

        return [
            'datasets' => [
                [
                    'label' => 'Rata-rata capaian',
                    'data' => array_map(fn (array $baris): float => $baris['rata_rata'], $rows),
                    'backgroundColor' => '#2563eb',
                ],
            ],
            'labels' => array_map(
                fn (array $baris): string => $baris['cpl_kode'].' — '.$baris['mk_nama'],
                $rows,
            ),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function getOptions(): ?array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'max' => 100,
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
        ];
    }

    /**
     * @return list<array{cpl_kode: string, mk_nama: string, kurikulum_nama: string, rata_rata: float, jumlah_mahasiswa: int}>
     */
    public function barisCplTertinggi(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return app(DashboardDosenPengampuService::class)->cplTertinggi($user, self::JUMLAH_CPL);
    }
}
