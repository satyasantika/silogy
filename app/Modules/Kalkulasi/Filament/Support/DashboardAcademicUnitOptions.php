<?php

namespace App\Modules\Kalkulasi\Filament\Support;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;

class DashboardAcademicUnitOptions
{
    /**
     * Unit yang ditetapkan ke user beserta seluruh keturunannya.
     *
     * @return array<string, string>
     */
    public static function forUser(?User $user = null): array
    {
        $user ??= auth()->user();

        if ($user === null) {
            return [];
        }

        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return AcademicUnit::query()
                ->orderBy('nama')
                ->get()
                ->mapWithKeys(fn (AcademicUnit $unit): array => [$unit->id => $unit->nama_lengkap])
                ->all();
        }

        $ids = collect();

        foreach (AcademicUnitScope::userAssignedUnitIds($user) as $unitId) {
            $ids = $ids->merge(AcademicUnitScope::descendantIdsIncludingSelf($unitId));
        }

        if ($ids->isEmpty()) {
            return [];
        }

        return AcademicUnit::query()
            ->whereIn('id', $ids->unique()->values())
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (AcademicUnit $unit): array => [$unit->id => $unit->nama_lengkap])
            ->all();
    }
}
