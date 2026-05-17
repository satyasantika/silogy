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
            && $this->hasAnyKelolaUnitPermission($user);
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

    protected function canManageUnit(User $user, AcademicUnit $unit): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        $permission = $this->permissionForType($unit->type);

        if (! $permission || ! $user->can($permission)) {
            return false;
        }

        return AcademicUnitScope::userHasPivotToUnitOrAncestor($user, $unit);
    }

    protected function permissionForType(string $type): ?string
    {
        return match ($type) {
            'university' => 'kelola_universitas',
            'faculty' => 'kelola_fakultas',
            'department' => 'kelola_jurusan',
            'study_program' => 'kelola_prodi',
            default => null,
        };
    }

    protected function hasAnyKelolaUnitPermission(User $user): bool
    {
        return $user->can('kelola_universitas')
            || $user->can('kelola_fakultas')
            || $user->can('kelola_jurusan')
            || $user->can('kelola_prodi');
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
