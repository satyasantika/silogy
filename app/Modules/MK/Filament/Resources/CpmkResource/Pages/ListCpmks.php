<?php

namespace App\Modules\MK\Filament\Resources\CpmkResource\Pages;

use App\Modules\MK\Filament\Resources\CpmkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCpmks extends ListRecords
{
    protected static string $resource = CpmkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
