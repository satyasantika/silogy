<?php

namespace App\Modules\Kurikulum\Filament\Widgets;

use App\Models\User;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Services\DashboardTimKurikulumService;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI pembuka dashboard Tim Kurikulum: keberadaan kurikulum yang dikelola
 * dan kurikulum yang sedang dikerjakan, masing-masing menjadi jalan pintas
 * ke daftar kurikulum dan ke profil lulusannya.
 */
class KurikulumKpiWidget extends StatsOverviewWidget
{
    use CanAccessDashboardWidgets;

    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    /**
     * Angkanya hanya berubah setelah kurikulum dibuat/dihapus atau kurikulum
     * yang dikerjakan diganti — tidak perlu polling 5 detik (default Filament)
     * yang menembak query berulang untuk setiap tab dashboard yang terbuka.
     */
    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Kurikulum';

    public static function canView(): bool
    {
        return static::canViewDashboardWidgets()
            && static::isDashboardTimKurikulum();
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

        $jumlah = app(DashboardTimKurikulumService::class)->jumlahKurikulum($user);
        $kurikulum = KurikulumTerpilih::current();

        return [
            Stat::make('Banyaknya Kurikulum', (string) $jumlah)
                ->description($jumlah > 0 ? 'Kelola daftar kurikulum' : 'Belum ada kurikulum — mulai susun')
                ->descriptionIcon(Heroicon::OutlinedRectangleStack)
                ->color($jumlah > 0 ? 'primary' : 'warning')
                ->url(KurikulumResource::getUrl('index')),

            Stat::make('Kurikulum Dikerjakan', $kurikulum?->nama ?? 'Belum dipilih')
                ->description($this->deskripsiKurikulumDikerjakan($kurikulum))
                ->descriptionIcon(Heroicon::OutlinedIdentification)
                ->color($kurikulum === null ? 'warning' : 'success')
                ->url($this->urlKurikulumDikerjakan($kurikulum)),
        ];
    }

    protected function deskripsiKurikulumDikerjakan(?Kurikulum $kurikulum): string
    {
        if ($kurikulum === null) {
            return 'Pilih kurikulum lewat banner halaman kurikulum';
        }

        return ($kurikulum->academicUnit?->isProdi() ?? false)
            ? 'Kelola profil lulusan'
            : KurikulumTerpilih::unitHierarchyLabel($kurikulum->academicUnit);
    }

    /**
     * Profil lulusan hanya ada pada kurikulum program studi — kurikulum
     * fakultas/universitas diarahkan kembali ke daftar kurikulum supaya
     * KPI tidak menautkan halaman yang ditolak policy.
     */
    protected function urlKurikulumDikerjakan(?Kurikulum $kurikulum): string
    {
        return ($kurikulum?->academicUnit?->isProdi() ?? false)
            ? ProfilLulusanResource::getUrl('index')
            : KurikulumResource::getUrl('index');
    }
}
