<?php

namespace App\Modules\Penilaian\Policies;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\Penilaian\Models\KomponenPenilaian;

class KomponenPenilaianPolicy
{
    use HasKoordinatorMkScope;

    public function viewAny(User $user): bool
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return true;
        }

        if ($user->hasRole('Dosen Pengampu') && ! $this->isAdminUnit($user)) {
            return false;
        }

        if (! $user->can('kelola_komponen_penilaian')) {
            return false;
        }

        return static::scopedKoordinatorKelasMkIds($user)->isNotEmpty()
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
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return true;
        }

        if ($user->hasRole('Dosen Pengampu') && ! $this->isAdminUnit($user)) {
            return false;
        }

        if (! $user->can('kelola_komponen_penilaian')) {
            return false;
        }

        $komponenPenilaian->loadMissing('kelasMk');

        $kelasMk = $komponenPenilaian->kelasMk;

        if (! $kelasMk instanceof KelasMk) {
            return false;
        }

        return static::userCanManageKelasAsKoordinator($user, $kelasMk)
            || static::userCanManageKelasByAdminUnit($user, $kelasMk);
    }

    protected function isAdminUnit(User $user): bool
    {
        return $user->hasRole('Admin');
    }
}
