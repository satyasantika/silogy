<?php

namespace App\Modules\BoK\Policies;

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Policies\Concerns\AuthorizesTimKurikulumByUnit;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;

class BokPolicy
{
    use AuthorizesTimKurikulumByUnit;

    protected function kelolaPermission(): string
    {
        return 'kelola_bok';
    }

    public function view(User $user, Bok $bok): bool
    {
        return $this->update($user, $bok);
    }

    public function update(User $user, Bok $bok): bool
    {
        return $this->manage($user, $bok) || $this->manageKodeOnly($user, $bok);
    }

    /**
     * BoK milik unit lain (universitas/fakultas) yang tersingkap lewat
     * adaptasi MK — tidak dikelola penuh, tapi kode-nya boleh diseragamkan
     * per unit yang mengadaptasi (lihat BokResource::form()/EditBok).
     */
    protected function manageKodeOnly(User $user, Bok $bok): bool
    {
        if (! $user->can($this->kelolaPermission())) {
            return false;
        }

        return AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user)
            ->contains(fn (string $unitId): bool => CplBokAdaptasiScope::adaptedBokIds($unitId)->contains($bok->id));
    }

    public function delete(User $user, Bok $bok): bool
    {
        if (! $this->manage($user, $bok)) {
            return false;
        }

        return $bok->belumDiinteraksikan();
    }
}
