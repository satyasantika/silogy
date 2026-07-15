<?php

namespace App\Modules\MK\Policies;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\PenawaranMkScope;

class MkUnitPolicy
{
    public function viewAny(User $user): bool
    {
        if ($this->koordinatorMkDapatLihatDaftar($user)) {
            return true;
        }

        return $user->can('kelola_mk_unit')
            && AcademicUnitScope::scopedMkUnitProdiIdsFor($user)->isNotEmpty();
    }

    public function view(User $user, MkUnit $mkUnit): bool
    {
        if ($this->koordinatorMkPemilikPenawaran($user, $mkUnit)) {
            return true;
        }

        return $this->manage($user, $mkUnit);
    }

    public function create(User $user): bool
    {
        if ($this->koordinatorMkTanpaKelolaPenawaran($user)) {
            return false;
        }

        return $this->viewAny($user) && $user->can('kelola_mk_unit');
    }

    public function update(User $user, MkUnit $mkUnit): bool
    {
        if ($this->koordinatorMkTanpaKelolaPenawaran($user)) {
            return false;
        }

        return $this->manage($user, $mkUnit);
    }

    public function delete(User $user, MkUnit $mkUnit): bool
    {
        return $user->hasRole('Super Admin') && $this->manage($user, $mkUnit);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function restore(User $user, MkUnit $mkUnit): bool
    {
        return $this->update($user, $mkUnit);
    }

    public function forceDelete(User $user, MkUnit $mkUnit): bool
    {
        return $this->delete($user, $mkUnit);
    }

    public function restoreAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function replicate(User $user, MkUnit $mkUnit): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    public function manage(User $user, MkUnit $mkUnit): bool
    {
        if ($this->koordinatorMkTanpaKelolaPenawaran($user)) {
            return false;
        }

        if (! $user->can('kelola_mk_unit')) {
            return false;
        }

        $mkUnit->loadMissing('academicUnit');

        $unit = $mkUnit->academicUnit;

        if (! $unit instanceof AcademicUnit || ! $unit->isProdi()) {
            return false;
        }

        return AcademicUnitScope::scopedMkUnitProdiIdsFor($user)->contains($unit->id);
    }

    protected function koordinatorMkTanpaKelolaPenawaran(User $user): bool
    {
        return PenawaranMkScope::isKoordinatorMkOnly($user);
    }

    protected function koordinatorMkDapatLihatDaftar(User $user): bool
    {
        if (! PenawaranMkScope::isKoordinatorMkOnly($user)) {
            return false;
        }

        return AcademicUnitScope::userHasPivotOnUnitType($user, 'study_program');
    }

    protected function koordinatorMkPemilikPenawaran(User $user, MkUnit $mkUnit): bool
    {
        if (! PenawaranMkScope::isKoordinatorMkOnly($user)) {
            return false;
        }

        $mkUnit->loadMissing('mk');

        return $mkUnit->mk?->koordinator_mk_id === $user->id;
    }
}
