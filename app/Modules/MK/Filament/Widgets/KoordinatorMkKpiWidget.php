<?php

namespace App\Modules\MK\Filament\Widgets;

use App\Models\User;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource;
use App\Modules\MK\Services\DashboardKoordinatorMkService;
use App\Modules\MK\Support\MkTerpilih;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI pembuka dashboard Koordinator Mata Kuliah: banyaknya MK yang
 * dikoordinasikan dan MK yang sedang dikerjakan (MkTerpilih), masing-masing
 * menjadi jalan pintas ke halaman Mata Kuliah Koordinator.
 */
class KoordinatorMkKpiWidget extends StatsOverviewWidget
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
            && static::isDashboardKoordinatorMk();
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

        $jumlah = app(DashboardKoordinatorMkService::class)->jumlahMk($user);
        $mk = MkTerpilih::current();

        return [
            Stat::make('MK Dikoordinasikan', (string) $jumlah)
                ->description($jumlah > 0 ? 'Kelola mata kuliah koordinasi Anda' : 'Belum ada MK yang dikoordinasikan')
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color($jumlah > 0 ? 'primary' : 'warning')
                ->url(MataKuliahKoordinatorResource::getUrl('index')),

            Stat::make('MK Sedang Dikerjakan', $mk !== null ? MkTerpilih::label($mk) : 'Belum dipilih')
                ->description($mk === null ? 'Pilih dari halaman Mata Kuliah Koordinator' : 'Kelola CPMK, Sub-CPMK, dan Asesmen')
                ->descriptionIcon(Heroicon::OutlinedIdentification)
                ->color($mk === null ? 'warning' : 'success')
                ->url(MataKuliahKoordinatorResource::getUrl('index')),
        ];
    }
}
