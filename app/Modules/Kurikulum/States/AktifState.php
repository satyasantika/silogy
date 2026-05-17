<?php

namespace App\Modules\Kurikulum\States;

class AktifState extends KurikulumState
{
    public static string $name = 'aktif';

    public function transitionTargets(): array
    {
        return [];
    }

    public function canTransition(?string $toStateClass = null): bool
    {
        return false;
    }
}
