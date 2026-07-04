<?php

namespace App\Modules\MK\Filament\Resources\CpmkResource\Pages;

use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;

class EditCpmk extends BaseEditRecord
{
    protected static string $resource = CpmkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
