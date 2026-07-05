<?php

namespace App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource\Pages;

use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource;
use Filament\Resources\Pages\ListRecords;

class ListMataKuliahKoordinators extends ListRecords
{
    protected static string $resource = MataKuliahKoordinatorResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
