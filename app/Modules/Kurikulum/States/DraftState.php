<?php

namespace App\Modules\Kurikulum\States;

class DraftState extends KurikulumState
{
    public static string $name = 'draft';

    public function transitionTargets(): array
    {
        if ($this->kurikulum()->academicUnit->isProdi()) {
            return [ProfilLulusanState::class];
        }

        return [CplState::class];
    }

    public function canTransition(?string $toStateClass = null): bool
    {
        return true;
    }
}
