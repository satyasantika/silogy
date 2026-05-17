<?php

namespace App\Modules\CPL\Policies;

use App\Modules\Kurikulum\Policies\Concerns\AuthorizesTimKurikulumByUnit;

class CplPolicy
{
    use AuthorizesTimKurikulumByUnit;

    protected function kelolaPermission(): string
    {
        return 'kelola_cpl';
    }
}
