<?php

namespace App\Modules\Auth\Policies;

use App\Models\User;

/**
 * Pengaturan pengguna (/users) eksklusif untuk Super Admin.
 * Role lain (termasuk admin unit dan dosen) tidak dapat melihat
 * maupun mengelola daftar pengguna.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function delete(User $user, User $model): bool
    {
        if (! $user->hasRole('Super Admin')) {
            return false;
        }

        return ! $model->hasDependentRecords();
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
        return $user->hasRole('Super Admin') && $user->can('kelola_permission');
    }
}
