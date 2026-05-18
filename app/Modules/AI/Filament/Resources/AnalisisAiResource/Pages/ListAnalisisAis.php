<?php

namespace App\Modules\AI\Filament\Resources\AnalisisAiResource\Pages;

use App\Modules\AI\Filament\Resources\AnalisisAiResource;
use Filament\Resources\Pages\ListRecords;

class ListAnalisisAis extends ListRecords
{
    protected static string $resource = AnalisisAiResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
