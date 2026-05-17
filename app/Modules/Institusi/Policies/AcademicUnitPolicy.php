<?php

namespace App\Modules\Institusi\Policies;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;

class AcademicUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isSuperAdmin($user)
            || $user->can('kelola_universitas')
            || $user->can('kelola_fakultas')
            || $user->can('kelola_jurusan')
            || $user->can('kelola_prodi');
    }

    public function view(User $user, AcademicUnit $academicUnit): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, AcademicUnit $academicUnit): bool
    {
        if ($this->isSuperAdmin($user)) {
            return true;
        }

        return match ($academicUnit->type) {
            'university' => $user->can('kelola_universitas'),
            'faculty' => $user->can('kelola_fakultas'),
            'department' => $user->can('kelola_jurusan'),
            'study_program' => $user->can('kelola_prodi'),
            default => false,
        };
    }

    public function delete(User $user, AcademicUnit $academicUnit): bool
    {
        return $this->update($user, $academicUnit);
    }

    public function deleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function restore(User $user, AcademicUnit $academicUnit): bool
    {
        return $this->update($user, $academicUnit);
    }

    public function forceDelete(User $user, AcademicUnit $academicUnit): bool
    {
        return $this->update($user, $academicUnit);
    }

    public function restoreAny(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function replicate(User $user, AcademicUnit $academicUnit): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return $this->viewAny($user);
    }

    protected function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }
}
