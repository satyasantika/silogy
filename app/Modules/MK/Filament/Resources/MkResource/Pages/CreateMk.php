<?php

namespace App\Modules\MK\Filament\Resources\MkResource\Pages;

use App\Modules\Kurikulum\Filament\Support\Concerns\AssignsKurikulumTerpilihOnCreate;
use App\Modules\MK\Filament\Resources\MkResource;
use App\Support\Filament\Pages\BaseCreateRecord;

class CreateMk extends BaseCreateRecord
{
    use AssignsKurikulumTerpilihOnCreate {
        mutateFormDataBeforeCreate as assignKurikulumTerpilih;
    }

    protected static string $resource = MkResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->assignKurikulumTerpilih($data);

        $data['state'] = 'draft';
        $data['sks'] = (int) ($data['sks_teori'] ?? 0)
            + (int) ($data['sks_praktik'] ?? 0)
            + (int) ($data['sks_lapangan'] ?? 0);

        return $data;
    }
}
