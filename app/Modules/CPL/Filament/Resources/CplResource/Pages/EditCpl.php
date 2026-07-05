<?php

namespace App\Modules\CPL\Filament\Resources\CplResource\Pages;

use App\Modules\CPL\Filament\Resources\CplResource;
use App\Support\Filament\Pages\BaseSimpleEditRecord;
use Filament\Actions\DeleteAction;

class EditCpl extends BaseSimpleEditRecord
{
    protected static string $resource = CplResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->belumDiinteraksikan()),
        ];
    }
}
