<?php

namespace App\Modules\CPL\Filament\Resources\CplResource\Pages;

use App\Modules\CPL\Filament\Resources\CplResource;
use App\Modules\Kurikulum\Filament\Support\Concerns\AssignsKurikulumTerpilihOnCreate;
use App\Support\Filament\Pages\BaseCreateRecord;

class CreateCpl extends BaseCreateRecord
{
    use AssignsKurikulumTerpilihOnCreate;

    protected static string $resource = CplResource::class;
}
