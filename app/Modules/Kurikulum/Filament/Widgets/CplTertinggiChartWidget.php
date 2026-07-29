<?php

namespace App\Modules\Kurikulum\Filament\Widgets;

use App\Models\User;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\Kurikulum\Services\DashboardTimKurikulumService;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Capaian CPL tertinggi pada unit kerja tim kurikulum, lintas kurikulum
 * (tidak terikat kurikulum terpilih maupun satu semester) — pelengkap
 * "Capaian CPL per Unit" yang selalu satu unit + satu semester.
 */
class CplTertinggiChartWidget extends ChartWidget
{
    use CanAccessDashboardWidgets;

    /** Jumlah CPL teratas yang ditampilkan. */
    public const JUMLAH_CPL = 5;

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    /**
     * Peringkat baru berubah setelah kalkulasi CPL dijalankan, sedangkan
     * agregasinya menyapu hasil_cpl_mk — jangan biarkan polling 5 detik
     * (default Filament) mengulanginya terus-menerus.
     */
    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Capaian CPL Tertinggi (lintas kurikulum)';

    protected ?string $maxHeight = '320px';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return static::canViewDashboardWidgets()
            && static::isDashboardTimKurikulum();
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->heading;
    }

    public function getDescription(): string|Htmlable|null
    {
        return $this->barisCplTertinggi() === []
            ? 'Belum ada hasil kalkulasi CPL yang bisa diperingkat.'
            : sprintf('%d CPL dengan rerata capaian tertinggi dari seluruh kurikulum pada unit kerja Anda.', self::JUMLAH_CPL);
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
                fn (array $baris): string => $baris['cpl_kode'].' — '.$baris['kurikulum_nama'],
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
     * @return list<array{cpl_kode: string, kurikulum_nama: string, unit_nama: string, rata_rata: float, jumlah_mahasiswa: int}>
     */
    public function barisCplTertinggi(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return app(DashboardTimKurikulumService::class)->cplTertinggi($user, self::JUMLAH_CPL);
    }
}
