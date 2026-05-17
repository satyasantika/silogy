<?php

namespace App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages;

use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAcademicUnits extends ListRecords
{
    protected static string $resource = AcademicUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
