<?php

namespace App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource\Pages;

use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMahasiswa extends EditRecord
{
    protected static string $resource = MahasiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
