<?php

namespace App\Modules\Penilaian\Policies;

use App\Models\User;
use App\Modules\MK\Models\Mk;

class PenilaianDosenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Dosen Pengampu') && $user->can('input_nilai');
    }

    public function view(User $user, Mk $mk): bool
    {
        return $this->viewAny($user);
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
