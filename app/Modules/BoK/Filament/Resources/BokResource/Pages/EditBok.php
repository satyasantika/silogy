<?php

namespace App\Modules\BoK\Filament\Resources\BokResource\Pages;

use App\Modules\BoK\Filament\Resources\BokResource;
use App\Support\Filament\Pages\BaseSimpleEditRecord;
use Filament\Actions\DeleteAction;

class EditBok extends BaseSimpleEditRecord
{
    protected static string $resource = BokResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
