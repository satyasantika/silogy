<?php

namespace App\Modules\Auth\Policies;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Institusi\Support\AcademicUnitScope;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        if (app('impersonate')->isImpersonating()) {
            return true;
        }

        return $this->hasAnyKelolaUserPermission($user);
    }

    public function view(User $user, User $model): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $this->canManageTargetUser($user, $model);
    }

    public function create(User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $this->hasAnyKelolaUserPermission($user)
            && AcademicUnitScope::userHasAnyPivot($user);
    }

    public function update(User $user, User $model): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $this->canManageTargetUser($user, $model);
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->hasRole('Super Admin') && ! $user->hasRole('Super Admin')) {
            return false;
        }

        if ($model->hasDependentRecords()) {
            return false;
        }

        return $this->update($user, $model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function restore(User $user, User $model): bool
    {
        return $this->update($user, $model);
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $this->delete($user, $model);
    }

    public function restoreAny(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function replicate(User $user, User $model): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function assignPermissions(User $user, User $model): bool
    {
        return $user->can('kelola_permission');
    }

    protected function canManageTargetUser(User $admin, User $target): bool
    {
        if (! $this->hasAnyKelolaUserPermission($admin)) {
            return false;
        }

        $pivots = AcademicUnitUser::query()
            ->where('user_id', $target->id)
            ->with('academicUnit')
            ->get();

        foreach ($pivots as $pivot) {
            if ($pivot->academicUnit && $this->canKelolaUserAtUnit($admin, $pivot->academicUnit)) {
                return true;
            }
        }

        return false;
    }

    protected function canKelolaUserAtUnit(User $admin, AcademicUnit $unit): bool
    {
        $permission = match ($unit->type) {
            'university' => 'kelola_user_universitas',
            'faculty' => 'kelola_user_fakultas',
            'department' => 'kelola_user_jurusan',
            'study_program' => 'kelola_user_prodi',
            default => null,
        };

        if (! $permission || ! $admin->can($permission)) {
            return false;
        }

        return AcademicUnitScope::userHasPivotToUnitOrAncestor($admin, $unit);
    }

    protected function hasAnyKelolaUserPermission(User $user): bool
    {
        return $user->can('kelola_user_universitas')
            || $user->can('kelola_user_fakultas')
            || $user->can('kelola_user_jurusan')
            || $user->can('kelola_user_prodi');
    }
}
