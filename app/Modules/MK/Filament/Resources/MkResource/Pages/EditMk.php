<?php

namespace App\Modules\MK\Filament\Resources\MkResource\Pages;

use App\Modules\MK\Filament\Resources\MkResource;
use App\Support\Filament\Pages\BaseSimpleEditRecord;
use Filament\Actions\DeleteAction;

class EditMk extends BaseSimpleEditRecord
{
    protected static string $resource = MkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['sks'] = (int) ($data['sks_teori'] ?? 0)
            + (int) ($data['sks_praktik'] ?? 0)
            + (int) ($data['sks_lapangan'] ?? 0);

        return $data;
    }
}
