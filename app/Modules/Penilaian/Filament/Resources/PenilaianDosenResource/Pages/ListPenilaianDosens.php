<?php

namespace App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource\Pages;

use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use Filament\Resources\Pages\ListRecords;

class ListPenilaianDosens extends ListRecords
{
    protected static string $resource = PenilaianDosenResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
