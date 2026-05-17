<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages;

use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\States\DraftState;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateKurikulum extends CreateRecord
{
    protected static string $resource = KurikulumResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['state'] = DraftState::class;
        $data['dibuat_oleh'] = Auth::id();

        return $data;
    }
}
