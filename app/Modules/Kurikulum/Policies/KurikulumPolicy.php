<?php

namespace App\Modules\Kurikulum\Policies;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Models\Kurikulum;

class KurikulumPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $user->can('kelola_kurikulum')
            && AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user)->isNotEmpty();
    }

    public function view(User $user, Kurikulum $kurikulum): bool
    {
        return $this->manage($user, $kurikulum);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Kurikulum $kurikulum): bool
    {
        return $this->manage($user, $kurikulum);
    }

    public function delete(User $user, Kurikulum $kurikulum): bool
    {
        return $user->hasRole('Super Admin') && $this->manage($user, $kurikulum);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function restore(User $user, Kurikulum $kurikulum): bool
    {
        return $this->update($user, $kurikulum);
    }

    public function forceDelete(User $user, Kurikulum $kurikulum): bool
    {
        return $this->delete($user, $kurikulum);
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function replicate(User $user, Kurikulum $kurikulum): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function manage(User $user, Kurikulum $kurikulum): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if (! $user->can('kelola_kurikulum')) {
            return false;
        }

        $kurikulum->loadMissing('academicUnit');

        return AcademicUnitScope::userIsTimKurikulumOnUnit($user, $kurikulum->academicUnit);
    }
}
