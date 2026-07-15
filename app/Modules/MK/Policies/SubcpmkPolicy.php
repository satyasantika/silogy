<?php

namespace App\Modules\MK\Policies;

use App\Models\User;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Subcpmk;

class SubcpmkPolicy
{
    use HasKoordinatorMkScope;

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Auditor Mutu')) {
            return true;
        }

        return $user->can('kelola_subcpmk')
            && static::scopedKoordinatorMkIds($user)->isNotEmpty();
    }

    public function view(User $user, Subcpmk $subcpmk): bool
    {
        return $this->manage($user, $subcpmk);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Subcpmk $subcpmk): bool
    {
        return $this->manage($user, $subcpmk);
    }

    public function delete(User $user, Subcpmk $subcpmk): bool
    {
        return $this->manage($user, $subcpmk);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function restore(User $user, Subcpmk $subcpmk): bool
    {
        return $this->update($user, $subcpmk);
    }

    public function forceDelete(User $user, Subcpmk $subcpmk): bool
    {
        return $this->delete($user, $subcpmk);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function replicate(User $user, Subcpmk $subcpmk): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    protected function manage(User $user, Subcpmk $subcpmk): bool
    {
        if ($user->hasRole('Auditor Mutu')) {
            return true;
        }

        if (! $user->can('kelola_subcpmk')) {
            return false;
        }

        $subcpmk->loadMissing('mkCpmk.cpmk');

        $mkId = $subcpmk->mkCpmk?->cpmk?->mk_id;

        return $mkId !== null && static::userCanManageMkAsKoordinator($user, $mkId);
    }
}
