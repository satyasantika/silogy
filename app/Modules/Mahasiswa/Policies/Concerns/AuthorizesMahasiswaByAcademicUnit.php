<?php

namespace App\Modules\Mahasiswa\Policies\Concerns;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Mahasiswa\Models\Mahasiswa;

trait AuthorizesMahasiswaByAcademicUnit
{
    protected function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    protected function isAuditor(User $user): bool
    {
        return $user->hasRole('Auditor Mutu');
    }

    /**
     * Admin dengan penugasan pada unit level universitas.
     */
    protected function isAdminUniversitas(User $user): bool
    {
        if (! $user->hasRole('Admin')) {
            return false;
        }

        return $user->academicUnitUsers()
            ->whereHas('academicUnit', fn ($query) => $query->where('type', 'university'))
            ->exists();
    }

    protected function isAdminDenganPenugasan(User $user): bool
    {
        return $user->hasRole('Admin') && AcademicUnitScope::userHasAnyPivot($user);
    }

    protected function canViewMahasiswaModule(User $user): bool
    {
        if ($this->isSuperAdmin($user) || $this->isAuditor($user)) {
            return true;
        }

        if ($this->isAdminDenganPenugasan($user)) {
            return true;
        }

        return $user->hasRole('Pimpinan') && AcademicUnitScope::userHasAnyPivot($user);
    }

    protected function canAccessMahasiswa(User $user, Mahasiswa $mahasiswa): bool
    {
        if ($this->isSuperAdmin($user) || $this->isAuditor($user)) {
            return true;
        }

        $unit = $mahasiswa->academicUnit;

        if (! $unit || $unit->type !== 'study_program') {
            return false;
        }

        return AcademicUnitScope::userHasPivotToUnitOrAncestor($user, $unit);
    }

    public function canAccessStudyProgram(User $user, ?string $academicUnitId): bool
    {
        if ($this->isSuperAdmin($user) || $this->isAuditor($user)) {
            return true;
        }

        if (! $academicUnitId) {
            return false;
        }

        $unit = AcademicUnit::query()->find($academicUnitId);

        if (! $unit || $unit->type !== 'study_program') {
            return false;
        }

        return AcademicUnitScope::userHasPivotToUnitOrAncestor($user, $unit);
    }
}
