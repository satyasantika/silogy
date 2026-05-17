<?php

namespace App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource\Pages;

use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMahasiswas extends ListRecords
{
    protected static string $resource = MahasiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
