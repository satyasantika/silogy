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
        $kurikulumId = $this->kurikulum()->id;

        $hasMkUnit = MkUnit::query()
            ->where('kurikulum_id', $kurikulumId)
            ->exists();

        $hasCplMk = CplMk::query()
            ->whereHas('mk', fn ($query) => $query->where('kurikulum_id', $kurikulumId))
            ->exists();

        return $hasMkUnit && $hasCplMk;
    }
}
