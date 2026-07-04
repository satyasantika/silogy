<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages;

use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Support\Filament\Pages\BaseSimpleEditRecord;
use Filament\Actions\DeleteAction;

class EditKurikulum extends BaseSimpleEditRecord
{
    protected static string $resource = KurikulumResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord()->belumDigunakanDiTabelLain()),
        ];
    }
}
