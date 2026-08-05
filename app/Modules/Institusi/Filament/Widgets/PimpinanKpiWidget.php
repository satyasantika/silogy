<?php

namespace App\Modules\Institusi\Filament\Widgets;

use App\Models\User;
use App\Modules\Institusi\Services\DashboardPimpinanService;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\Kurikulum\Filament\Pages\DaftarKurikulumPimpinan;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI pembuka dashboard Pimpinan: jumlah kurikulum aktif dan jumlah unit
 * dalam jangkauan kepemimpinannya (unit tempat ia berstatus pimpinan
 * beserta seluruh keturunannya).
 */
class PimpinanKpiWidget extends StatsOverviewWidget
{
    use CanAccessDashboardWidgets;

    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    /**
     * Angkanya hanya berubah setelah kurikulum/penugasan unit berubah —
     * tidak perlu polling 5 detik (default Filament).
     */
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Ringkasan Kepemimpinan';

    public static function canView(): bool
    {
        return static::canViewDashboardWidgets()
            && static::isDashboardPimpinan();
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

        $service = app(DashboardPimpinanService::class);
        $jumlahKurikulum = $service->jumlahKurikulum($user);
        $jumlahUnit = $service->jumlahUnitDikelola($user);

        $daftarKurikulumUrl = DaftarKurikulumPimpinan::getUrl();

        return [
            Stat::make('Kurikulum Aktif', (string) $jumlahKurikulum)
                ->description($jumlahKurikulum > 0 ? 'Buka daftar kurikulum' : 'Belum ada kurikulum pada unit ini')
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color($jumlahKurikulum > 0 ? 'primary' : 'warning')
                ->url($daftarKurikulumUrl),

            Stat::make('Unit Dikelola', (string) $jumlahUnit)
                ->description($jumlahUnit > 0 ? 'Termasuk seluruh unit di bawahnya' : 'Belum ada unit yang ditugaskan')
                ->descriptionIcon(Heroicon::OutlinedBuildingLibrary)
                ->color($jumlahUnit > 0 ? 'success' : 'warning')
                ->url($daftarKurikulumUrl),
        ];
    }
}
