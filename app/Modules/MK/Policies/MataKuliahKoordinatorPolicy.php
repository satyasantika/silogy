<?php

namespace App\Modules\MK\Policies;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;

/**
 * Akses halaman /mata-kuliah-koordinator: cukup punya role
 * "Koordinator Mata Kuliah" (boleh multi-role) dan penugasan nyata
 * sebagai koordinator pada minimal satu MK atau kelas. Pivot
 * academic_unit_users ke prodi tidak diperlukan — penetapan di /mks
 * (mk.koordinator_mk_id + KoordinatorMkRoleSync) sudah cukup.
 */
class MataKuliahKoordinatorPolicy
{
    public function viewAny(User $user): bool
    {
        if (! $user->hasRole('Koordinator Mata Kuliah')) {
            return false;
        }

        return $this->userDitugaskanSebagaiKoordinator($user);
    }

    public function view(User $user, Mk $mk): bool
    {
        if (! $user->hasRole('Koordinator Mata Kuliah')) {
            return false;
        }

        return $this->userDitugaskanSebagaiKoordinatorPadaMk($user, $mk->id);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Mk $mk): bool
    {
        return false;
    }

    public function delete(User $user, Mk $mk): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Mk $mk): bool
    {
        return false;
    }

    public function forceDelete(User $user, Mk $mk): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function replicate(User $user, Mk $mk): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    /**
     * Penugasan nyata sebagai koordinator — langsung ke mk / kelas_mk,
     * bukan scopedKoordinatorMkIds() (yang untuk Admin/Super Admin
     * mengembang ke seluruh MK unit).
     */
    protected function userDitugaskanSebagaiKoordinator(User $user): bool
    {
        return Mk::query()->where('koordinator_mk_id', $user->id)->exists()
            || KelasMk::query()->where('koordinator_mk_id', $user->id)->exists();
    }

    protected function userDitugaskanSebagaiKoordinatorPadaMk(User $user, string $mkId): bool
    {
        if (Mk::query()->whereKey($mkId)->where('koordinator_mk_id', $user->id)->exists()) {
            return true;
        }

        return KelasMk::query()
            ->where('koordinator_mk_id', $user->id)
            ->whereHas('mkUnit', fn ($query) => $query->where('mk_id', $mkId))
            ->exists();
    }
}
