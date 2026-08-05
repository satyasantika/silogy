<?php

namespace App\Modules\Kalkulasi\Filament\Support\Concerns;

use App\Models\User;

trait CanAccessDashboardWidgets
{
    /**
     * @return list<string>
     */
    protected static function dashboardRoleNames(): array
    {
        return [
            'Super Admin',
            'Pimpinan',
            'Admin',
            'Tim Kurikulum',
            'Auditor Mutu',
            'Koordinator Mata Kuliah',
            'Dosen Pengampu',
        ];
    }

    public static function canViewDashboardWidgets(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if (! $user->can('lihat_dashboard')) {
            return false;
        }

        return $user->hasAnyRole(static::dashboardRoleNames());
    }

    /**
     * Dashboard bekerja dalam mode Pimpinan: KPI kepemimpinan + capaian
     * teratas lintas kurikulum pada unit kepemimpinannya (beserta seluruh
     * keturunannya), tanpa filter unit/semester/CPL. Kalah prioritas hanya
     * dari Super Admin.
     */
    public static function isDashboardPimpinan(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if (! $user->hasRole('Pimpinan')) {
            return false;
        }

        return ! $user->hasRole('Super Admin');
    }

    /**
     * Dashboard bekerja dalam mode Tim Kurikulum: KPI kurikulum + capaian
     * teratas lintas kurikulum, tanpa filter unit/semester/CPL. Mengikuti
     * role aktif (hasRole() sudah difilter ActiveRole), sehingga user yang
     * juga Pimpinan/Admin tetap mendapat dashboard CPL ber-filter.
     */
    public static function isDashboardTimKurikulum(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if (! $user->hasRole('Tim Kurikulum')) {
            return false;
        }

        return ! $user->hasAnyRole(['Super Admin', 'Pimpinan', 'Admin', 'Auditor Mutu']);
    }

    /**
     * Dashboard bekerja dalam mode Koordinator Mata Kuliah: KPI MK yang
     * dikoordinasikan + capaian CPL/MK teratas discope ke MK tersebut,
     * bukan lintas unit. Kalah prioritas dari Tim Kurikulum/Pimpinan/Admin/
     * Super Admin/Auditor Mutu bila user punya lebih dari satu role.
     */
    public static function isDashboardKoordinatorMk(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if (! $user->hasRole('Koordinator Mata Kuliah')) {
            return false;
        }

        return ! $user->hasAnyRole(['Super Admin', 'Pimpinan', 'Admin', 'Auditor Mutu', 'Tim Kurikulum']);
    }

    /**
     * Dashboard bekerja dalam mode Dosen Pengampu: KPI MK yang diampu +
     * capaian CPL/MK teratas discope ke kelas yang diajar. Kalah prioritas
     * dari Koordinator Mata Kuliah dan role yang lebih tinggi.
     */
    public static function isDashboardDosenPengampu(?User $user = null): bool
    {
        $user ??= auth()->user();

        if ($user === null) {
            return false;
        }

        if (! $user->hasRole('Dosen Pengampu')) {
            return false;
        }

        return ! $user->hasAnyRole(['Super Admin', 'Pimpinan', 'Admin', 'Auditor Mutu', 'Tim Kurikulum', 'Koordinator Mata Kuliah']);
    }

    /**
     * Widget CPL ber-filter (Capaian CPL per Unit & drill-down MK Unit)
     * beserta form filternya — disembunyikan pada mode Tim Kurikulum,
     * Koordinator Mata Kuliah, dan Dosen Pengampu (masing-masing punya
     * widget capaian CPL sendiri yang sudah discope).
     */
    public static function canViewDashboardCplWidgets(?User $user = null): bool
    {
        return static::canViewDashboardWidgets($user)
            && ! static::isDashboardTimKurikulum($user)
            && ! static::isDashboardKoordinatorMk($user)
            && ! static::isDashboardDosenPengampu($user);
    }
}
