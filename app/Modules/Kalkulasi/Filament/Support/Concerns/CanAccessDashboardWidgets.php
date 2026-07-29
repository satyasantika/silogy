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
     * Widget CPL ber-filter (Capaian CPL per Unit & drill-down MK Unit)
     * beserta form filternya — disembunyikan pada mode Tim Kurikulum.
     */
    public static function canViewDashboardCplWidgets(?User $user = null): bool
    {
        return static::canViewDashboardWidgets($user)
            && ! static::isDashboardTimKurikulum($user);
    }
}
