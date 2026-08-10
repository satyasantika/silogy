<?php

namespace App\Modules\Kalender\Policies;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;

class SemesterPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function view(User $user, Semester $semester): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function create(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function update(User $user, Semester $semester): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function delete(User $user, Semester $semester): bool
    {
        return $this->isSuperAdmin($user) && ! $semester->sedangDigunakan();
    }

    public function deleteAny(User $user): bool
    {
        return $this->isSuperAdmin($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    protected function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }
}
