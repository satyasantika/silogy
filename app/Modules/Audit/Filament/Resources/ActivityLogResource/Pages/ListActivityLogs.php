<?php

namespace App\Modules\Audit\Filament\Resources\ActivityLogResource\Pages;

use App\Modules\Audit\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityLogs extends ListRecords
{
    protected static string $resource = ActivityLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
