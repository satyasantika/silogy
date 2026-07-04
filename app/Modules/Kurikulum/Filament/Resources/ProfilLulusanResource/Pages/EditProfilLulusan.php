<?php

namespace App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource\Pages;

use App\Modules\Kurikulum\Filament\Concerns\HasImporIndikatorProfilLulusan;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Support\Filament\Pages\BaseSimpleEditRecord;
use Filament\Actions\DeleteAction;

class EditProfilLulusan extends BaseSimpleEditRecord
{
    use HasImporIndikatorProfilLulusan;

    protected static string $resource = ProfilLulusanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeImporMassalAction()
                ->label('Impor indikator')
                ->visible(fn (): bool => ProfilLulusanResource::canEdit($this->getRecord())),
            DeleteAction::make(),
        ];
    }
}
