<?php

namespace App\Modules\MK\Policies;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\PerubahanCpmkRequest;

class PerubahanCpmkRequestPolicy
{
    use HasKoordinatorMkScope;

    public function viewAny(User $user): bool
    {
        if ($user->hasAnyRole(['Super Admin', 'Auditor Mutu', 'Admin'])) {
            return true;
        }

        // Tim Kurikulum melihat kotak masuk unitnya; Koordinator MK melihat
        // usulannya sendiri (query resource yang membatasi keduanya).
        return AcademicUnitScope::timKurikulumPivotUnitIdsFor($user)->isNotEmpty()
            || $user->can('kelola_cpmk');
    }

    public function view(User $user, PerubahanCpmkRequest $usulan): bool
    {
        return $usulan->diajukan_oleh_id === $user->id
            || $this->berwenangMeninjau($user, $usulan);
    }

    public function create(User $user): bool
    {
        return $user->can('kelola_cpmk')
            && static::scopedKoordinatorMkIds($user)->isNotEmpty();
    }

    /**
     * Menyetujui hanya boleh oleh Tim Kurikulum unit MK (atau unit induknya),
     * dan tidak boleh oleh pengusulnya sendiri — persetujuan yang bisa
     * diberikan sendiri bukan persetujuan.
     */
    public function setujui(User $user, PerubahanCpmkRequest $usulan): bool
    {
        return $usulan->menunggu()
            && $usulan->diajukan_oleh_id !== $user->id
            && $user->can('setujui_perubahan_cpmk')
            && $this->berwenangMeninjau($user, $usulan);
    }

    public function tolak(User $user, PerubahanCpmkRequest $usulan): bool
    {
        return $this->setujui($user, $usulan);
    }

    public function batalkan(User $user, PerubahanCpmkRequest $usulan): bool
    {
        return $usulan->menunggu() && $usulan->diajukan_oleh_id === $user->id;
    }

    public function update(User $user, PerubahanCpmkRequest $usulan): bool
    {
        return false;
    }

    public function delete(User $user, PerubahanCpmkRequest $usulan): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    private function berwenangMeninjau(User $user, PerubahanCpmkRequest $usulan): bool
    {
        $usulan->loadMissing('mk.academicUnit');
        $mk = $usulan->mk;

        if (! $mk instanceof Mk) {
            return false;
        }

        $unit = $mk->academicUnit;

        return $unit !== null
            && AcademicUnitScope::userIsTimKurikulumOnUnitOrAncestor($user, $unit);
    }
}
