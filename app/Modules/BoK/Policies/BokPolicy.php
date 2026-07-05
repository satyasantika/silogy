<?php

namespace App\Modules\BoK\Policies;

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\Kurikulum\Policies\Concerns\AuthorizesTimKurikulumByUnit;

class BokPolicy
{
    use AuthorizesTimKurikulumByUnit;

    protected function kelolaPermission(): string
    {
        return 'kelola_bok';
    }

    public function delete(User $user, Bok $bok): bool
    {
        if (! $this->manage($user, $bok)) {
            return false;
        }

        return $bok->belumDiinteraksikan();
    }
}
