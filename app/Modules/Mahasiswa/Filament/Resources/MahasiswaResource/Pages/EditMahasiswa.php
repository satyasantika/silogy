<?php

namespace App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource\Pages;

use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;

class EditMahasiswa extends BaseEditRecord
{
    protected static string $resource = MahasiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
