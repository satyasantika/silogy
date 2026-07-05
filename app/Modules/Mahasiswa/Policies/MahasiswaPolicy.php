<?php

namespace App\Modules\Mahasiswa\Policies;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Mahasiswa\Policies\Concerns\AuthorizesMahasiswaByAcademicUnit;

class MahasiswaPolicy
{
    use AuthorizesMahasiswaByAcademicUnit;

    public function viewAny(User $user): bool
    {
        return $this->canViewMahasiswaModule($user);
    }

    public function view(User $user, Mahasiswa $mahasiswa): bool
    {
        return $this->canAccessMahasiswa($user, $mahasiswa);
    }

    public function create(User $user): bool
    {
        if ($this->isSuperAdmin($user) || $this->isAuditor($user)) {
            return true;
        }

        return $this->isAdminDenganPenugasan($user)
            || ($user->hasRole('Pimpinan') && AcademicUnitScope::userHasAnyPivot($user));
    }

    public function update(User $user, Mahasiswa $mahasiswa): bool
    {
        return $this->canAccessMahasiswa($user, $mahasiswa);
    }

    public function delete(User $user, Mahasiswa $mahasiswa): bool
    {
        return $this->isSuperAdmin($user) || $this->isAdminUniversitas($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->isSuperAdmin($user) || $this->isAdminUniversitas($user);
    }

    public function restore(User $user, Mahasiswa $mahasiswa): bool
    {
        return $this->update($user, $mahasiswa);
    }

    public function forceDelete(User $user, Mahasiswa $mahasiswa): bool
    {
        return $this->delete($user, $mahasiswa);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function replicate(User $user, Mahasiswa $mahasiswa): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }
}
