<?php

namespace App\Modules\MK\Filament\Resources\MkUnitResource\Pages;

use App\Modules\MK\Filament\Concerns\HasAdaptasiMkMassal;
use App\Modules\MK\Filament\Concerns\HasImporUpdateMkUnitMassal;
use App\Modules\MK\Filament\Concerns\HasSalinMkUnitMassal;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use Filament\Resources\Pages\ListRecords;

class ListMkUnits extends ListRecords
{
    use HasAdaptasiMkMassal;
    use HasImporUpdateMkUnitMassal;
    use HasSalinMkUnitMassal;

    protected static string $resource = MkUnitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->makeSalinMkUnitAction()
                ->visible(fn (): bool => MkUnitResource::canCreate()),
            $this->makeUpdateMkUnitMassalAction()
                ->visible(fn (): bool => MkUnitResource::canCreate()),
            $this->makeAdaptasiMkMassalAction()
                ->visible(fn (): bool => MkUnitResource::canCreate()),
        ];
    }
}
