<?php

namespace App\Modules\Institusi\Policies;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;

class AcademicUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user)
            || $this->isAuditor($user)
            || AcademicUnitScope::userHasAnyPivot($user);
    }

    public function view(User $user, AcademicUnit $academicUnit): bool
    {
        if ($this->isSuperAdmin($user) || $this->isAuditor($user)) {
            return true;
        }

        return AcademicUnitScope::userCanViewUnit($user, $academicUnit);
    }

    public function create(User $user): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return AcademicUnitScope::userHasAnyPivot($user)
            && $user->can('kelola_unit');
    }

    public function createUnit(User $user, string $type, ?string $parentId = null): bool
    {
        $unit = new AcademicUnit([
            'type' => $type,
            'parent_id' => $parentId,
        ]);

        if ($parentId) {
            $unit->setRelation('parent', AcademicUnit::find($parentId));
        }

        return $this->canManageUnit($user, $unit);
    }

    public function update(User $user, AcademicUnit $academicUnit): bool
    {
        return $this->canManageUnit($user, $academicUnit);
    }

    public function delete(User $user, AcademicUnit $academicUnit): bool
    {
        if ($academicUnit->hasDependentRecords()) {
            return false;
        }

        return $this->isSuperAdmin($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function restore(User $user, AcademicUnit $academicUnit): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function forceDelete(User $user, AcademicUnit $academicUnit): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function restoreAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function replicate(User $user, AcademicUnit $academicUnit): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    /**
     * Admin mengelola unit yang berada KETAT DI BAWAH penugasannya:
     * pivot pada salah satu ancestor unit target (bukan unit itu sendiri).
     * Universitas tidak berinduk, sehingga hanya Super Admin yang bisa —
     * setara aturan lama yang tidak memberi kelola_universitas ke admin.
     */
    protected function canManageUnit(User $user, AcademicUnit $unit): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        if (! $user->can('kelola_unit')) {
            return false;
        }

        $unit->loadMissing('parent');

        if ($unit->parent === null) {
            return false;
        }

        return AcademicUnitScope::userHasPivotToUnitOrAncestor($user, $unit->parent);
    }

    protected function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    protected function isAuditor(User $user): bool
    {
        return $user->hasRole('Auditor Mutu');
    }
}
