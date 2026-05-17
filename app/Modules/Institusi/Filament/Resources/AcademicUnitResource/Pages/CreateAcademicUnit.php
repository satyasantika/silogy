<?php

namespace App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages;

use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Policies\AcademicUnitPolicy;
use Filament\Resources\Pages\CreateRecord;

class CreateAcademicUnit extends CreateRecord
{
    protected static string $resource = AcademicUnitResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();

        abort_unless(
            app(AcademicUnitPolicy::class)->createUnit(
                auth()->user(),
                $data['type'] ?? '',
                $data['parent_id'] ?? null,
            ),
            403,
        );
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (($data['type'] ?? null) === 'university') {
            $data['parent_id'] = null;
        }

        return $data;
    }
}
