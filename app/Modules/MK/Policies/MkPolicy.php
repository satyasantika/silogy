<?php

namespace App\Modules\MK\Policies;

use App\Modules\Kurikulum\Policies\Concerns\AuthorizesTimKurikulumByUnit;

class MkPolicy
{
    use AuthorizesTimKurikulumByUnit;

    protected function kelolaPermission(): string
    {
        return 'kelola_mk';
    }
}
