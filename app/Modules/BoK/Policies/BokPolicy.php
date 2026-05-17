<?php

namespace App\Modules\BoK\Policies;

use App\Modules\Kurikulum\Policies\Concerns\AuthorizesTimKurikulumByUnit;

class BokPolicy
{
    use AuthorizesTimKurikulumByUnit;

    protected function kelolaPermission(): string
    {
        return 'kelola_bok';
    }
}
