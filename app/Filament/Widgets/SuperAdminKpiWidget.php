<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPI pembuka dashboard Super Admin: ringkasan jumlah unit akademik,
 * pengguna, dan mahasiswa — tiga entitas yang memang jadi cakupan menu
 * Super Admin (lihat App\Support\Filament\DelegasiMenu).
 */
class SuperAdminKpiWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        // Ikuti role aktif switcher (hasRole), bukan kepemilikan role.
        return $user instanceof User && $user->hasRole('Super Admin');
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $jumlahUnit = AcademicUnit::count();
        $jumlahUnitAktif = AcademicUnit::where('status', 'aktif')->count();

        $jumlahPengguna = User::count();

        $jumlahMahasiswa = Mahasiswa::count();
        $jumlahMahasiswaAktif = Mahasiswa::where('status', 'aktif')->count();

        return [
            Stat::make('Unit Akademik', (string) $jumlahUnit)
                ->description("{$jumlahUnitAktif} aktif")
                ->descriptionIcon(Heroicon::OutlinedBuildingLibrary)
                ->color('primary')
                ->url(AcademicUnitResource::getUrl('index')),

            Stat::make('Pengguna', (string) $jumlahPengguna)
                ->description('Terdaftar di sistem')
                ->descriptionIcon(Heroicon::OutlinedUsers)
                ->color('primary')
                ->url(UserResource::getUrl('index')),

            Stat::make('Mahasiswa', (string) $jumlahMahasiswa)
                ->description("{$jumlahMahasiswaAktif} aktif")
                ->descriptionIcon(Heroicon::OutlinedAcademicCap)
                ->color('primary')
                ->url(MahasiswaResource::getUrl('index')),
        ];
    }
}
