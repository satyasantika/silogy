<?php

namespace App\Modules\CPL\Filament\Resources\CplResource\Pages;

use App\Modules\CPL\Filament\Resources\CplResource;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Kurikulum\Filament\Support\Concerns\AssignsKurikulumTerpilihOnCreate;
use App\Support\Filament\Concerns\MenarikPilihanPemetaan;
use App\Support\Filament\Pages\BaseCreateRecord;

class CreateCpl extends BaseCreateRecord
{
    use AssignsKurikulumTerpilihOnCreate {
        mutateFormDataBeforeCreate as assignKurikulumTerpilih;
    }
    use MenarikPilihanPemetaan;

    protected static string $resource = CplResource::class;

    /** @var list<string> */
    private array $profilLulusanIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->assignKurikulumTerpilih($data);
        [$data, $this->profilLulusanIds] = $this->tarikPilihanPemetaan($data, 'profil_lulusan_ids');

        return $data;
    }

    /**
     * Sama dengan kolom "profil lulusan" pada impor massal: profil yang dipilih
     * langsung menjadi pemetaan CPL ↔ Profil Lulusan.
     */
    protected function afterCreate(): void
    {
        /** @var Cpl $cpl */
        $cpl = $this->getRecord();

        foreach (CplResource::profilLulusanOptions() as $id => $label) {
            if (in_array((string) $id, $this->profilLulusanIds, true)) {
                CplProfilLulusan::query()->firstOrCreate([
                    'cpl_id' => $cpl->id,
                    'profil_lulusan_id' => $id,
                ]);
            }
        }
    }
}
