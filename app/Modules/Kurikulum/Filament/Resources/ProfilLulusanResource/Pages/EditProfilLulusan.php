<?php

namespace App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages;

use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Support\Filament\Pages\BaseEditRecord;
use Filament\Actions\DeleteAction;

class EditProfilLulusan extends BaseEditRecord
{
    protected static string $resource = ProfilLulusanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
