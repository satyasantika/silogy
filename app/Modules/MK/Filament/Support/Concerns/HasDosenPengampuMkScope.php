<?php

namespace App\Modules\MK\Filament\Support\Concerns;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use Illuminate\Support\Collection;

trait HasDosenPengampuMkScope
{
    /**
     * MK yang benar-benar diampu user lewat kelas_mk.dosen_pengampu_id.
     *
     * @return Collection<int, string>
     */
    public static function scopedDiampuMkIds(User $user): Collection
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return Mk::query()->pluck('id');
        }

        // Admin: seluruh MK pada unit dalam jangkauan penugasannya.
        if ($user->hasRole('Admin')) {
            return Mk::query()
                ->whereIn('academic_unit_id', AcademicUnitScope::managedUnitIdsFor($user))
                ->pluck('id');
        }

        return KelasMk::query()
            ->where('dosen_pengampu_id', $user->id)
            ->whereHas('mkUnit')
            ->with('mkUnit')
            ->get()
            ->pluck('mkUnit.mk_id')
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, string>
     */
    public static function scopedDiampuKelasMkIds(User $user): Collection
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return KelasMk::query()->pluck('id');
        }

        if ($user->hasRole('Admin')) {
            return KelasMk::query()
                ->whereHas('mkUnit', fn ($query) => $query->whereIn(
                    'academic_unit_id',
                    AcademicUnitScope::managedUnitIdsFor($user),
                ))
                ->pluck('id');
        }

        return KelasMk::query()
            ->where('dosen_pengampu_id', $user->id)
            ->pluck('id');
    }
}
