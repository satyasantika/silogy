<?php

namespace App\Modules\Penilaian\Policies;

use App\Models\User;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\Penilaian\Models\KomponenPenilaian;

class KomponenPenilaianPolicy
{
    use HasKoordinatorMkScope;

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Auditor Mutu')) {
            return true;
        }

        if ($user->hasRole('Dosen Pengampu') && ! $this->isAdminUnit($user)) {
            return false;
        }

        if (! $user->can('kelola_komponen_penilaian')) {
            return false;
        }

        return static::scopedKoordinatorMkIds($user)->isNotEmpty()
            || $user->hasRole('Admin');
    }

    public function view(User $user, KomponenPenilaian $komponenPenilaian): bool
    {
        return $this->manage($user, $komponenPenilaian);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, KomponenPenilaian $komponenPenilaian): bool
    {
        return $this->manage($user, $komponenPenilaian);
    }

    public function delete(User $user, KomponenPenilaian $komponenPenilaian): bool
    {
        return $this->manage($user, $komponenPenilaian);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function restore(User $user, KomponenPenilaian $komponenPenilaian): bool
    {
        return $this->update($user, $komponenPenilaian);
    }

    public function forceDelete(User $user, KomponenPenilaian $komponenPenilaian): bool
    {
        return $this->delete($user, $komponenPenilaian);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function replicate(User $user, KomponenPenilaian $komponenPenilaian): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    protected function manage(User $user, KomponenPenilaian $komponenPenilaian): bool
    {
        if ($user->hasRole('Auditor Mutu')) {
            return true;
        }

        if ($user->hasRole('Dosen Pengampu') && ! $this->isAdminUnit($user)) {
            return false;
        }

        if (! $user->can('kelola_komponen_penilaian')) {
            return false;
        }

        $mkId = $komponenPenilaian->mk_id;

        if (blank($mkId)) {
            return false;
        }

        return static::userCanManageMkAsKoordinator($user, $mkId)
            || static::userCanManageMkByAdminUnit($user, $mkId);
    }

    protected function isAdminUnit(User $user): bool
    {
        return $user->hasRole('Admin');
    }
}
