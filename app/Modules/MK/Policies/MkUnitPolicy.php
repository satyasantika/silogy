<?php

namespace App\Modules\MK\Policies;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\MK\Models\MkUnit;

class MkUnitPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $user->can('kelola_mk_unit')
            && AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user)->isNotEmpty();
    }

    public function view(User $user, MkUnit $mkUnit): bool
    {
        return $this->manage($user, $mkUnit);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, MkUnit $mkUnit): bool
    {
        return $this->manage($user, $mkUnit);
    }

    public function delete(User $user, MkUnit $mkUnit): bool
    {
        return $user->hasRole('Super Admin') && $this->manage($user, $mkUnit);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function restore(User $user, MkUnit $mkUnit): bool
    {
        return $this->update($user, $mkUnit);
    }

    public function forceDelete(User $user, MkUnit $mkUnit): bool
    {
        return $this->delete($user, $mkUnit);
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function replicate(User $user, MkUnit $mkUnit): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function manage(User $user, MkUnit $mkUnit): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if (! $user->can('kelola_mk_unit')) {
            return false;
        }

        $mkUnit->loadMissing('academicUnit');

        $unit = $mkUnit->academicUnit;

        if (! $unit instanceof AcademicUnit) {
            return false;
        }

        return AcademicUnitScope::userIsTimKurikulumOnUnit($user, $unit);
    }
}
