<?php

namespace App\Modules\Kurikulum\States;

use App\Modules\CPL\Models\Cpl;

class CplState extends KurikulumState
{
    public static string $name = 'cpl';

    public function transitionTargets(): array
    {
        return [BokState::class];
    }

    public function canTransition(?string $toStateClass = null): bool
    {
        $unitId = $this->kurikulum()->academic_unit_id;

        if ($this->kurikulum()->academicUnit->isProdi()) {
            return Cpl::query()
                ->where('academic_unit_id', $unitId)
                ->whereHas('cplProfilLulusan', function ($query) {
                    $query->whereHas('profilLulusan', function ($profilQuery) {
                        $profilQuery->where('kurikulum_id', $this->kurikulum()->id);
                    });
                })
                ->exists();
        }

        return Cpl::query()
            ->where('academic_unit_id', $unitId)
            ->exists();
    }
}
