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
}
