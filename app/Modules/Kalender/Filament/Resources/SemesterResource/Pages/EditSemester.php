<?php

namespace App\Modules\Kalender\Filament\Resources\SemesterResource\Pages;

use App\Modules\Kalender\Filament\Resources\SemesterResource;
use App\Modules\Kalender\Models\Semester;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;

class EditSemester extends BaseEditRecord
{
    protected static string $resource = SemesterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (Semester $record): bool => ! $record->sedangDigunakan()),
        ];
    }
}
