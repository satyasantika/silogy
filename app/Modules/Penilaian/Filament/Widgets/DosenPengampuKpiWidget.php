<?php

namespace App\Modules\Penilaian\Filament\Widgets;

use App\Models\User;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use App\Modules\Penilaian\Services\DashboardDosenPengampuService;
use App\Modules\Penilaian\Support\MkDiampuTerpilih;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI pembuka dashboard Dosen Pengampu: banyaknya MK yang diampu dan MK
 * yang sedang dikerjakan (MkDiampuTerpilih), masing-masing menjadi jalan
 * pintas ke halaman Penilaian.
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

        $jumlah = app(DashboardDosenPengampuService::class)->jumlahMkDiampu($user);
        $mk = MkDiampuTerpilih::current();

        return [
            Stat::make('MK Diampu', (string) $jumlah)
                ->description($jumlah > 0 ? 'Kelola penilaian mata kuliah Anda' : 'Belum ada MK yang diampu')
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color($jumlah > 0 ? 'primary' : 'warning')
                ->url(PenilaianDosenResource::getUrl('index')),

            Stat::make('MK Sedang Dikerjakan', $mk?->nama ?? 'Belum dipilih')
                ->description($mk === null ? 'Pilih lewat widget di bawah' : 'Kelola penilaian mahasiswa')
                ->descriptionIcon(Heroicon::OutlinedIdentification)
                ->color($mk === null ? 'warning' : 'success')
                ->url(PenilaianDosenResource::getUrl('index')),
        ];
    }
}
