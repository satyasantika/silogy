<?php

namespace App\Modules\Kurikulum\States;

use App\Modules\CPL\Models\CplMk;
use App\Modules\MK\Models\MkUnit;

class MkState extends KurikulumState
{
    public static string $name = 'mk';

    public function transitionTargets(): array
    {
        return [SetdosenmkState::class];
    }

    public function canTransition(?string $toStateClass = null): bool
    {
        $unitId = $this->kurikulum()->academic_unit_id;

        $hasMkUnit = MkUnit::query()
            ->where('academic_unit_id', $unitId)
            ->exists();

        $hasCplMk = CplMk::query()
            ->whereHas('mk', fn ($query) => $query->where('academic_unit_id', $unitId))
            ->exists();

        return $hasMkUnit && $hasCplMk;
    }
}
