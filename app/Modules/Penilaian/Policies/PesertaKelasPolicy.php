<?php

namespace App\Modules\Penilaian\Policies;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;

/**
 * Akses halaman "Mahasiswa" (peserta kelas MK per mata kuliah terpilih)
 * untuk Koordinator Mata Kuliah — mengelola pendaftaran peserta lewat
 * impor massal atau tarik dari Sintesys, dibatasi pada MK yang ia
 * koordinasikan (atau seluruh MK unit penugasan untuk Admin).
 */
class PesertaKelasPolicy
{
    use HasKoordinatorMkScope;

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Auditor Mutu')) {
            return true;
        }

        if (! $user->can('kelola_peserta_kelas')) {
            return false;
        }

        return static::scopedKoordinatorMkIds($user)->isNotEmpty()
            || $user->hasRole('Admin');
    }

    public function view(User $user, KelasMk $kelasMk): bool
    {
        return $this->manage($user, $kelasMk);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, KelasMk $kelasMk): bool
    {
        return $this->manage($user, $kelasMk);
    }

    public function delete(User $user, KelasMk $kelasMk): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, KelasMk $kelasMk): bool
    {
        return false;
    }

    public function forceDelete(User $user, KelasMk $kelasMk): bool
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

    public function replicate(User $user, KelasMk $kelasMk): bool
    {
        return false;
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    /**
     * Mengelola peserta kelas ini (impor massal / tarik Sintesys) sah bila
     * user berwenang mengelola MK pemilik kelas — sebagai koordinatornya
     * atau sebagai Admin pada unit penugasan MK tersebut.
     */
    public function manage(User $user, KelasMk $kelasMk): bool
    {
        if ($user->hasRole('Auditor Mutu')) {
            return true;
        }

        if (! $user->can('kelola_peserta_kelas')) {
            return false;
        }

        $kelasMk->loadMissing('mkUnit');
        $mkId = $kelasMk->mkUnit?->mk_id;

        if (blank($mkId)) {
            return false;
        }

        return static::userCanManageMkAsKoordinator($user, $mkId)
            || static::userCanManageMkByAdminUnit($user, $mkId);
    }

    /**
     * Dipakai aksi Impor massal / Tarik dari Sintesys pada halaman List
     * (belum ada record KelasMk konkret — otorisasi berdasarkan MK terpilih).
     */
    public function manageMk(User $user, string $mkId): bool
    {
        if ($user->hasRole('Auditor Mutu')) {
            return true;
        }

        if (! $user->can('kelola_peserta_kelas')) {
            return false;
        }

        return static::userCanManageMkAsKoordinator($user, $mkId)
            || static::userCanManageMkByAdminUnit($user, $mkId);
    }
}
