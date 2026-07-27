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
        $kurikulumId = $this->kurikulum()->id;

        if ($this->kurikulum()->academicUnit->isProdi()) {
            return Cpl::query()
                ->where('kurikulum_id', $kurikulumId)
                ->whereHas('cplProfilLulusan', function ($query) use ($kurikulumId) {
                    $query->whereHas('profilLulusan', function ($profilQuery) use ($kurikulumId) {
                        $profilQuery->where('kurikulum_id', $kurikulumId);
                    });
                })
                ->exists();
        }

        return Cpl::query()
            ->where('kurikulum_id', $kurikulumId)
            ->exists();
    }
}
