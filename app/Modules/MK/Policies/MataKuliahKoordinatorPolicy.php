<?php

namespace App\Modules\MK\Policies;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Support\PenawaranMkScope;

class MataKuliahKoordinatorPolicy
{
    use HasKoordinatorMkScope;

    public function viewAny(User $user): bool
    {
        if (! PenawaranMkScope::isKoordinatorMkOnly($user)) {
            return false;
        }

        return AcademicUnitScope::userHasPivotOnUnitType($user, 'study_program');
    }

    public function view(User $user, Mk $mk): bool
    {
        return $this->viewAny($user)
            && static::userCanManageMkAsKoordinator($user, $mk->id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Mk $mk): bool
    {
        return false;
    }

    public function delete(User $user, Mk $mk): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Mk $mk): bool
    {
        return false;
    }

    public function forceDelete(User $user, Mk $mk): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Mk $mk): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
