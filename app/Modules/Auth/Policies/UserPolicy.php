<?php

namespace App\Modules\Auth\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function view(User $user, User $model): bool
    {
        return $this->canManageUsers($user);
    }

    public function create(User $user): bool
    {
        return $this->canManageUsers($user);
    }

    public function update(User $user, User $model): bool
    {
        return $this->canManageUsers($user);
    }

    public function delete(User $user, User $model): bool
    {
        if ($model->hasRole('Super Admin') && ! $user->hasRole('Super Admin')) {
            return false;
        }

        return $this->canManageUsers($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canManageUsers($user);
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
        return $this->viewAny($user);
    }

    public function replicate(User $user, User $model): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    protected function canManageUsers(User $user): bool
    {
        return $user->hasRole('Super Admin')
            || $user->can('kelola_user')
            || $user->can('kelola_user_universitas')
            || $user->can('kelola_user_fakultas')
            || $user->can('kelola_user_jurusan')
            || $user->can('kelola_user_prodi');
    }
}
