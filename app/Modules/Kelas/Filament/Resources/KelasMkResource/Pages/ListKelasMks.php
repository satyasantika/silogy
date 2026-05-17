<?php

namespace App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages;

use App\Modules\Kelas\Filament\Resources\KelasMkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKelasMks extends ListRecords
{
    protected static string $resource = KelasMkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
