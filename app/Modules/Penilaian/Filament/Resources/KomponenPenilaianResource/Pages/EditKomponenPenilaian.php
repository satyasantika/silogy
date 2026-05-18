<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages;

use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\Concerns\ValidatesBobotKomponenSama100;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKomponenPenilaian extends EditRecord
{
    use ValidatesBobotKomponenSama100;

    protected static string $resource = KomponenPenilaianResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $this->validateBobotKomponenSama100($this->form->getState());
    }
}
