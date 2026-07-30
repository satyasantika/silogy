<?php

namespace App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages;

use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Services\KurikulumStateSyncService;
use App\Modules\Kurikulum\States\DraftState;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\Pages\BaseCreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateKurikulum extends BaseCreateRecord
{
    protected static string $resource = KurikulumResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['state'] = DraftState::class;
        $data['dibuat_oleh'] = Auth::id();

        return $data;
    }

    protected function afterCreate(): void
    {
        app(KurikulumStateSyncService::class)->sync($this->record);

        // Kurikulum baru langsung jadi "kurikulum yang dikerjakan" — tanpa
        // ini, session tetap menunjuk kurikulum lama dan CPL/BoK/MK yang
        // tampil berikutnya adalah milik kurikulum lama, bukan yang baru.
        KurikulumTerpilih::set($this->record->id);
    }
}
