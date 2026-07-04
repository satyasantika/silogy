<?php

namespace App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages;

use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;

class EditAcademicUnit extends BaseEditRecord
{
    protected static string $resource = AcademicUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['type'] ?? null) === 'university') {
            $data['parent_id'] = null;
        }

        return $data;
    }
}
