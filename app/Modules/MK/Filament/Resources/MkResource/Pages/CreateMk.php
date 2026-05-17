<?php

namespace App\Modules\MK\Filament\Resources\MkResource\Pages;

use App\Modules\MK\Filament\Resources\MkResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMk extends CreateRecord
{
    protected static string $resource = MkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['state'] = 'draft';
        $data['sks'] = (int) ($data['sks_teori'] ?? 0)
            + (int) ($data['sks_praktik'] ?? 0)
            + (int) ($data['sks_lapangan'] ?? 0);

        return $data;
    }
}
