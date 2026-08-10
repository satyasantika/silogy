<?php

namespace App\Modules\Kalender\Filament\Resources\SemesterResource\Pages;

use App\Modules\Kalender\Filament\Resources\SemesterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSemesters extends ListRecords
{
    protected static string $resource = SemesterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
