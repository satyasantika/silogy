<?php

namespace App\Modules\Kurikulum\States;

class ProfilLulusanState extends KurikulumState
{
    public static string $name = 'profil_lulusan';

    public function transitionTargets(): array
    {
        return [CplState::class];
    }

    public function canTransition(?string $toStateClass = null): bool
    {
        return $this->kurikulum()
            ->profilLulusan()
            ->whereHas('indikators')
            ->exists();
    }
}
