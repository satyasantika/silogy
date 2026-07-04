<?php

namespace App\Modules\MK\Filament\Resources\SubcpmkResource\Pages;

use App\Modules\MK\Filament\Resources\SubcpmkResource;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;

class EditSubcpmk extends BaseEditRecord
{
    protected static string $resource = SubcpmkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
