<?php

namespace App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages;

use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAcademicUnit extends CreateRecord
{
    protected static string $resource = AcademicUnitResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['type'] ?? null) === 'university') {
            $data['parent_id'] = null;
        }

        return $data;
    }
}
