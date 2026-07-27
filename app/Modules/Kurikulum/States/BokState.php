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
        $kurikulumId = $this->kurikulum()->id;

        return CplBok::query()
            ->whereHas('cpl', fn ($query) => $query->where('kurikulum_id', $kurikulumId))
            ->exists();
    }
}
