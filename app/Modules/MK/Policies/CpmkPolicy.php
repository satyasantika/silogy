<?php

namespace App\Modules\MK\Policies;

use App\Models\User;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Cpmk;

class CpmkPolicy
{
    use HasKoordinatorMkScope;

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Auditor Mutu')) {
            return true;
        }

        return $user->can('kelola_cpmk')
            && static::scopedKoordinatorMkIds($user)->isNotEmpty();
    }

    public function view(User $user, Cpmk $cpmk): bool
    {
        return $this->manage($user, $cpmk);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Cpmk $cpmk): bool
    {
        return $this->manage($user, $cpmk);
    }

    public function delete(User $user, Cpmk $cpmk): bool
    {
        if (! $this->manage($user, $cpmk)) {
            return false;
        }

        return $cpmk->belumDiinteraksikan();
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function restore(User $user, Cpmk $cpmk): bool
    {
        return $this->update($user, $cpmk);
    }

    public function forceDelete(User $user, Cpmk $cpmk): bool
    {
        return $this->delete($user, $cpmk);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function replicate(User $user, Cpmk $cpmk): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    protected function manage(User $user, Cpmk $cpmk): bool
    {
        if ($user->hasRole('Auditor Mutu')) {
            return true;
        }

        if (! $user->can('kelola_cpmk')) {
            return false;
        }

        return static::userCanManageMkAsKoordinator($user, $cpmk->mk_id);
    }
}
