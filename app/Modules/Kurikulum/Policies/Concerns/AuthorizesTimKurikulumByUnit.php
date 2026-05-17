<?php

namespace App\Modules\Kurikulum\Policies\Concerns;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use Illuminate\Database\Eloquent\Model;

trait AuthorizesTimKurikulumByUnit
{
    abstract protected function kelolaPermission(): string;

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $user->can($this->kelolaPermission())
            && AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user)->isNotEmpty();
    }

    public function view(User $user, Model $model): bool
    {
        return $this->manage($user, $model);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->manage($user, $model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->hasRole('Super Admin') && $this->manage($user, $model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function restore(User $user, Model $model): bool
    {
        return $this->update($user, $model);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->delete($user, $model);
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function replicate(User $user, Model $model): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function manage(User $user, Model $model): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if (! $user->can($this->kelolaPermission())) {
            return false;
        }

        if (! method_exists($model, 'academicUnit')) {
            return false;
        }

        $model->loadMissing('academicUnit');

        $unit = $model->academicUnit;

        if (! $unit instanceof AcademicUnit) {
            return false;
        }

        return AcademicUnitScope::userIsTimKurikulumOnUnit($user, $unit);
    }
}
