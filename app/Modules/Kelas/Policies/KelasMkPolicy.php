<?php

namespace App\Modules\Kelas\Policies;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kelas\Models\KelasMk;

class KelasMkPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return true;
        }

        if ($user->hasRole('Dosen Pengampu')) {
            return $user->can('kelola_kelas');
        }

        return $user->can('kelola_kelas') || $user->can('setdosen_mk');
    }

    public function view(User $user, KelasMk $kelasMk): bool
    {
        return $this->canAccessKelas($user, $kelasMk);
    }

    /**
     * Kelas MK dikelola paling jauh di level prodi: pembuatnya harus
     * punya penugasan LANGSUNG pada unit program studi (Admin prodi
     * atau Tim Kurikulum prodi).
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $user->can('kelola_kelas')
            && AcademicUnitScope::userHasPivotOnUnitType($user, 'study_program');
    }

    public function update(User $user, KelasMk $kelasMk): bool
    {
        return $this->canAccessKelas($user, $kelasMk);
    }

    public function delete(User $user, KelasMk $kelasMk): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if (! $user->can('kelola_kelas')) {
            return false;
        }

        return $this->canManageByUnit($user, $kelasMk);
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function restore(User $user, KelasMk $kelasMk): bool
    {
        return $this->update($user, $kelasMk);
    }

    public function forceDelete(User $user, KelasMk $kelasMk): bool
    {
        return $this->delete($user, $kelasMk);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function replicate(User $user, KelasMk $kelasMk): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    /**
     * Penetapan / ubah dosen pengampu (permission setdosen_mk).
     */
    public function assignDosenPengampu(User $user, KelasMk $kelasMk): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if (! $user->can('setdosen_mk')) {
            return false;
        }

        return $this->canManageByUnit($user, $kelasMk)
            || $this->isTimKurikulumOnKelasUnit($user, $kelasMk);
    }

    protected function canAccessKelas(User $user, KelasMk $kelasMk): bool
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return true;
        }

        if ($user->hasRole('Dosen Pengampu') && ! $this->isAdminUnit($user)) {
            return $this->isDosenPemilik($user, $kelasMk) && $user->can('kelola_kelas');
        }

        if ($user->can('kelola_kelas') && $this->canManageByUnit($user, $kelasMk)) {
            return true;
        }

        if ($user->can('setdosen_mk') && (
            $this->canManageByUnit($user, $kelasMk)
            || $this->isTimKurikulumOnKelasUnit($user, $kelasMk)
        )) {
            return true;
        }

        return false;
    }

    protected function isAdminUnit(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    protected function canManageByUnit(User $user, KelasMk $kelasMk): bool
    {
        $unit = $this->resolveAcademicUnit($kelasMk);

        return $unit instanceof AcademicUnit
            && AcademicUnitScope::userHasPivotToUnitOrAncestor($user, $unit);
    }

    protected function isTimKurikulumOnKelasUnit(User $user, KelasMk $kelasMk): bool
    {
        $unit = $this->resolveAcademicUnit($kelasMk);

        return $unit instanceof AcademicUnit
            && AcademicUnitScope::userIsTimKurikulumOnUnit($user, $unit);
    }

    protected function isDosenPemilik(User $user, KelasMk $kelasMk): bool
    {
        return $user->hasRole('Dosen Pengampu')
            && $kelasMk->dosen_pengampu_id === $user->id;
    }

    protected function isKoordinatorPemilik(User $user, KelasMk $kelasMk): bool
    {
        return $user->hasRole('Koordinator Mata Kuliah')
            && $kelasMk->koordinator_mk_id === $user->id;
    }

    protected function resolveAcademicUnit(KelasMk $kelasMk): ?AcademicUnit
    {
        $kelasMk->loadMissing('mkUnit.academicUnit');

        return $kelasMk->mkUnit?->academicUnit;
    }
}
