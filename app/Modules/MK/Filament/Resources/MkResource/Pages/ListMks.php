<?php

namespace App\Modules\MK\Filament\Resources\MkResource\Pages;

use App\Modules\MK\Filament\Resources\MkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMks extends ListRecords
{
    protected static string $resource = MkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
