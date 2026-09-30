<?php

namespace App\Modules\MK\Policies;

use App\Models\User;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Support\GerbangPerubahanCpmk;
use App\Modules\MK\Support\MkTerpilih;

class CpmkPolicy
{
    use HasKoordinatorMkScope;

    public function viewAny(User $user): bool
    {
        if ($user->hasRole('Auditor Mutu')) {
            return true;
        }

        return $user->can('kelola_cpmk')
            && static::scopedKoordinatorMkIds($user)->isNotEmpty();
    }

    public function view(User $user, Cpmk $cpmk): bool
    {
        return $this->manage($user, $cpmk);
    }

    /**
     * Menyusun CPMK baru untuk semester berjalan adalah bentuk perubahan
     * CPMK — butuh persetujuan Tim Kurikulum bila semester itu sudah punya
     * CPMK. Memakai ulang CPMK semester lalu tidak lewat sini.
     */
    public function create(User $user): bool
    {
        return $this->viewAny($user) && $this->gerbangTerbuka($user);
    }

    public function update(User $user, Cpmk $cpmk): bool
    {
        return $this->manage($user, $cpmk)
            && $this->gerbangTerbuka($user, $cpmk);
    }

    public function delete(User $user, Cpmk $cpmk): bool
    {
        if (! $this->manage($user, $cpmk) || ! $this->gerbangTerbuka($user, $cpmk)) {
            return false;
        }

        return $cpmk->belumDiinteraksikan();
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function restore(User $user, Cpmk $cpmk): bool
    {
        return $this->update($user, $cpmk);
    }

    public function forceDelete(User $user, Cpmk $cpmk): bool
    {
        return $this->delete($user, $cpmk);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function replicate(User $user, Cpmk $cpmk): bool
    {
        return $this->create($user);
    }

    public function reorder(User $user): bool
    {
        return false;
    }

    /**
     * Gerbang persetujuan untuk MK + semester yang sedang dikerjakan. Hanya
     * berlaku pada konteks Koordinator MK — peran lain tidak punya semester
     * terpilih, dan kewenangan mereka sudah diperiksa di dalam gerbang.
     */
    protected function gerbangTerbuka(User $user, ?Cpmk $cpmk = null): bool
    {
        $mk = $cpmk instanceof Cpmk ? $cpmk->mk : MkTerpilih::current();

        if (! $mk instanceof Mk) {
            return true;
        }

        if (! SemesterTerpilih::berlakuUntukUser($user)) {
            return true;
        }

        return GerbangPerubahanCpmk::bolehUbah($mk, SemesterTerpilih::currentId($mk->id), $user);
    }

    protected function manage(User $user, Cpmk $cpmk): bool
    {
        if ($user->hasRole('Auditor Mutu')) {
            return true;
        }

        if (! $user->can('kelola_cpmk')) {
            return false;
        }

        return static::userCanManageMkAsKoordinator($user, $cpmk->mk_id);
    }
}
