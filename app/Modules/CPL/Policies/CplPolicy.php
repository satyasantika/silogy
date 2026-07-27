<?php

namespace App\Modules\CPL\Policies;

use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Policies\Concerns\AuthorizesTimKurikulumByUnit;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;

class CplPolicy
{
    use AuthorizesTimKurikulumByUnit;

    protected function kelolaPermission(): string
    {
        return 'kelola_cpl';
    }

    public function view(User $user, Cpl $cpl): bool
    {
        return $this->update($user, $cpl);
    }

    public function update(User $user, Cpl $cpl): bool
    {
        return $this->manage($user, $cpl) || $this->manageKodeOnly($user, $cpl);
    }

    /**
     * CPL milik unit lain (universitas/fakultas) yang tersingkap lewat
     * adaptasi MK — tidak dikelola penuh, tapi kode-nya boleh diseragamkan
     * per unit yang mengadaptasi (lihat CplResource::form()/EditCpl).
     */
    protected function manageKodeOnly(User $user, Cpl $cpl): bool
    {
        if (! $user->can($this->kelolaPermission())) {
            return false;
        }

        return AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user)
            ->contains(fn (string $unitId): bool => CplBokAdaptasiScope::adaptedCplIdsAcrossUnit($unitId)->contains($cpl->id));
    }

    public function delete(User $user, Cpl $cpl): bool
    {
        if (! $this->manage($user, $cpl)) {
            return false;
        }

        return $cpl->belumDiinteraksikan();
    }
}
