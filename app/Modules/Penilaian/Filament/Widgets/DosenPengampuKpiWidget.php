<?php

namespace App\Modules\Penilaian\Filament\Widgets;

use App\Models\User;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use App\Modules\Penilaian\Services\DashboardDosenPengampuService;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI pembuka dashboard Dosen Pengampu: banyaknya MK dan kelas yang diampu,
 * masing-masing menjadi jalan pintas ke halaman Pengampu MK.
 */
class DosenPengampuKpiWidget extends StatsOverviewWidget
{
    use CanAccessDashboardWidgets;

    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Mata Kuliah';

    public static function canView(): bool
    {
        return static::canViewDashboardWidgets()
            && static::isDashboardDosenPengampu();
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $service = app(DashboardDosenPengampuService::class);
        $jumlahMk = $service->jumlahMkDiampu($user);
        $jumlahKelas = $service->jumlahKelasDiampu($user);
        $url = PenilaianDosenResource::getUrl('index');

        return [
            Stat::make('MK Diampu', (string) $jumlahMk)
                ->description($jumlahMk > 0 ? 'Kelola penilaian mata kuliah Anda' : 'Belum ada MK yang diampu')
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color($jumlahMk > 0 ? 'primary' : 'warning')
                ->url($url),

            Stat::make('Kelas Diampu', (string) $jumlahKelas)
                ->description($jumlahKelas > 0 ? 'Kelas yang Anda ajar semester berjalan' : 'Belum ada kelas yang diampu')
                ->descriptionIcon(Heroicon::OutlinedIdentification)
                ->color($jumlahKelas > 0 ? 'success' : 'warning')
                ->url($url),
        ];
    }
}
