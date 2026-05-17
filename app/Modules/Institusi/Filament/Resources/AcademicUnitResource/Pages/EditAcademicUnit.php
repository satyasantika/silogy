<?php

namespace App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages;

use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAcademicUnit extends EditRecord
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
