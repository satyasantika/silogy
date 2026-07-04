<?php

namespace App\Modules\MK\Filament\Resources\MkUnitResource\Pages;

use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;

class EditMkUnit extends BaseEditRecord
{
    protected static string $resource = MkUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
