<?php

namespace App\Modules\Kelas\Policies;

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
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

        // Koordinator MK melihat kelas yang dikoordinasikannya
        // untuk menetapkan dosen pengampu.
        if ($user->hasRole('Koordinator Mata Kuliah')) {
            return true;
        }

        // Kelas MK dikelola Admin prodi — cek role/permission milik user
        // langsung (bukan role aktif switcher) agar menu tetap tampil
        // walau peran aktif Tim Kurikulum/Dosen dipilih.
        if ($this->userIsAdminProdi($user)) {
            return true;
        }

        return false;
    }

    /**
     * Admin prodi: penugasan langsung prodi + permission kelola_kelas
     * pada role Admin (generik atau legacy Admin Program Studi).
     */
    protected function userIsAdminProdi(User $user): bool
    {
        if (! AcademicUnitScope::userHasPivotOnUnitType($user, 'study_program')) {
            return false;
        }

        if (! ActiveRole::userOwnsPermission($user, 'kelola_kelas')) {
            return false;
        }

        foreach (['Admin', 'Admin Program Studi'] as $roleName) {
            if (ActiveRole::userOwnsRoleName($user, $roleName)) {
                return true;
            }
        }

        return false;
    }

    public function view(User $user, KelasMk $kelasMk): bool
    {
        return $this->canAccessKelas($user, $kelasMk);
    }

    /**
     * Pembuatan kelas MK per semester adalah tugas ADMIN PRODI:
     * role Admin dengan penugasan langsung pada unit program studi.
     */
    public function create(User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return ActiveRole::userOwnsPermission($user, 'kelola_kelas')
            && AcademicUnitScope::userHasPivotOnUnitType($user, 'study_program')
            && (
                ActiveRole::userOwnsRoleName($user, 'Admin')
                || ActiveRole::userOwnsRoleName($user, 'Admin Program Studi')
            );
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

        if (! ActiveRole::userOwnsPermission($user, 'kelola_kelas')) {
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
     * Penetapan dosen pengampu per kelas: Admin prodi (scope unit)
     * atau Koordinator MK kelas tersebut.
     */
    public function assignDosenPengampu(User $user, KelasMk $kelasMk): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if ($this->isKoordinatorPemilik($user, $kelasMk)) {
            return true;
        }

        return $this->isAdminUnit($user)
            && ActiveRole::userOwnsPermission($user, 'setdosen_mk')
            && $this->canManageByUnit($user, $kelasMk);
    }

    /**
     * Pendaftaran mahasiswa ke kelas (per NIM maupun impor massal):
     * Dosen Pengampu kelas tersebut atau Admin prodi.
     */
    public function kelolaMahasiswa(User $user, KelasMk $kelasMk): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        if ($this->isDosenPemilik($user, $kelasMk)) {
            return true;
        }

        return $this->isAdminUnit($user)
            && ActiveRole::userOwnsPermission($user, 'kelola_kelas')
            && $this->canManageByUnit($user, $kelasMk);
    }

    protected function canAccessKelas(User $user, KelasMk $kelasMk): bool
    {
        if ($user->hasRole(['Super Admin', 'Auditor Mutu'])) {
            return true;
        }

        if ($this->isKoordinatorPemilik($user, $kelasMk)) {
            return true;
        }

        if ($user->hasRole('Dosen Pengampu') && ! $this->isAdminUnit($user)) {
            return $this->isDosenPemilik($user, $kelasMk) && $user->can('kelola_kelas');
        }

        if ($this->userIsAdminProdi($user) && $this->canManageByUnit($user, $kelasMk)) {
            return true;
        }

        if (ActiveRole::userOwnsPermission($user, 'kelola_kelas') && $this->canManageByUnit($user, $kelasMk)) {
            return true;
        }

        if (ActiveRole::userOwnsPermission($user, 'setdosen_mk') && (
            $this->canManageByUnit($user, $kelasMk)
            || $this->isTimKurikulumOnKelasUnit($user, $kelasMk)
        )) {
            return true;
        }

        return false;
    }

    protected function isAdminUnit(User $user): bool
    {
        return ActiveRole::userOwnsRoleName($user, 'Admin')
            || ActiveRole::userOwnsRoleName($user, 'Admin Program Studi');
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
