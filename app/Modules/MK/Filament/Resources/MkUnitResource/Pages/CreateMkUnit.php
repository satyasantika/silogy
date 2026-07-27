<?php

namespace App\Modules\MK\Filament\Resources\MkUnitResource\Pages;

use App\Modules\Kurikulum\Filament\Support\Concerns\AssignsKurikulumTerpilihOnCreate;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Support\Filament\Pages\BaseCreateRecord;

class CreateMkUnit extends BaseCreateRecord
{
    use AssignsKurikulumTerpilihOnCreate;

    protected static string $resource = MkUnitResource::class;
}
