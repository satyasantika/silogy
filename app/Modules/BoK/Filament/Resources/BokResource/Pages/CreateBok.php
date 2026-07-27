<?php

namespace App\Modules\BoK\Filament\Resources\BokResource\Pages;

use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\Kurikulum\Filament\Support\Concerns\AssignsKurikulumTerpilihOnCreate;
use App\Support\Filament\Pages\BaseCreateRecord;

class CreateBok extends BaseCreateRecord
{
    use AssignsKurikulumTerpilihOnCreate;

    protected static string $resource = BokResource::class;
}
