<?php

namespace App\Modules\Kurikulum\States;

use App\Modules\CPL\Models\CplBok;

class BokState extends KurikulumState
{
    public static string $name = 'bok';

    public function transitionTargets(): array
    {
        return [MkState::class];
    }

    public function canTransition(?string $toStateClass = null): bool
    {
        $unitId = $this->kurikulum()->academic_unit_id;

        return CplBok::query()
            ->whereHas('cpl', fn ($query) => $query->where('academic_unit_id', $unitId))
            ->exists();
    }
}
