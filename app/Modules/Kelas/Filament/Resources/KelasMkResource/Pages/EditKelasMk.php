<?php

namespace App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages;

use App\Modules\Kelas\Filament\Resources\KelasMkResource;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Policies\KelasMkPolicy;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKelasMk extends EditRecord
{
    protected static string $resource = KelasMkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();
        /** @var KelasMk $record */
        $record = $this->getRecord();

        if ($user && ! app(KelasMkPolicy::class)->assignDosenPengampu($user, $record)) {
            $data['dosen_pengampu_id'] = $record->dosen_pengampu_id;
        }

        return $data;
    }
}
