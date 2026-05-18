<?php

namespace App\Modules\AI\Support;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Institusi\Support\AcademicUnitScope;

final class AnalisisAiUnitOptions
{
    /**
     * Unit yang boleh dipilih saat meminta analisis AI.
     *
     * @return array<string, string>
     */
    public static function forRequestForm(?User $user = null): array
    {
        $user ??= auth()->user();

        if ($user === null) {
            return [];
        }

        if ($user->hasRole('Super Admin')) {
            return AcademicUnit::query()
                ->orderBy('nama')
                ->pluck('nama', 'id')
                ->all();
        }

        $unitIds = collect();

        $pimpinanIds = AcademicUnitUser::query()
            ->where('user_id', $user->id)
            ->where('status_pimpinan', true)
            ->pluck('academic_unit_id');

        $unitIds = $unitIds->merge($pimpinanIds);

        if ($user->hasAnyRole([
            'Admin Universitas',
            'Admin Fakultas',
            'Admin Jurusan',
            'Admin Program Studi',
        ])) {
            $unitIds = $unitIds->merge(AcademicUnitScope::managedUnitIdsFor($user));
        }

        if ($unitIds->isEmpty()) {
            return [];
        }

        return AcademicUnit::query()
            ->whereIn('id', $unitIds->unique()->values())
            ->orderBy('nama')
            ->pluck('nama', 'id')
            ->all();
    }
}
