<?php

namespace App\Modules\CPL\Policies;

use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Kurikulum\Policies\Concerns\AuthorizesTimKurikulumByUnit;

class CplPolicy
{
    use AuthorizesTimKurikulumByUnit;

    protected function kelolaPermission(): string
    {
        return 'kelola_cpl';
    }

    public function delete(User $user, Cpl $cpl): bool
    {
        if (! $this->manage($user, $cpl)) {
            return false;
        }

        return $cpl->belumDiinteraksikan();
    }
}
