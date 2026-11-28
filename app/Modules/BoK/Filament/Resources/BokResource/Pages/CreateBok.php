<?php

namespace App\Modules\BoK\Filament\Resources\BokResource\Pages;

use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\CplBok;
use App\Modules\Kurikulum\Filament\Support\Concerns\AssignsKurikulumTerpilihOnCreate;
use App\Support\Filament\Concerns\MenarikPilihanPemetaan;
use App\Support\Filament\Pages\BaseCreateRecord;

class CreateBok extends BaseCreateRecord
{
    use AssignsKurikulumTerpilihOnCreate {
        mutateFormDataBeforeCreate as assignKurikulumTerpilih;
    }
    use MenarikPilihanPemetaan;

    protected static string $resource = BokResource::class;

    /** @var list<string> */
    private array $cplIds = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->assignKurikulumTerpilih($data);
        [$data, $this->cplIds] = $this->tarikPilihanPemetaan($data, 'cpl_ids');

        return $data;
    }

    /**
     * Sama dengan kolom "kode CPL terpetakan" pada impor massal: CPL yang
     * dipilih langsung menjadi pemetaan CPL ↔ BoK.
     */
    protected function afterCreate(): void
    {
        /** @var Bok $bok */
        $bok = $this->getRecord();
        $tersedia = BokResource::cplOptions();

        foreach ($this->cplIds as $cplId) {
            if (! array_key_exists($cplId, $tersedia)) {
                continue;
            }

            CplBok::query()->firstOrCreate(['cpl_id' => $cplId, 'bok_id' => $bok->id]);
        }
    }
}
