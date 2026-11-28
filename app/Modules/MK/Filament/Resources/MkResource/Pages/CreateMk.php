<?php

namespace App\Modules\MK\Filament\Resources\MkResource\Pages;

use App\Modules\CPL\Models\CplMk;
use App\Modules\Kurikulum\Filament\Support\Concerns\AssignsKurikulumTerpilihOnCreate;
use App\Modules\MK\Filament\Resources\MkResource;
use App\Modules\MK\Models\Mk;
use App\Support\Filament\Concerns\MenarikPilihanPemetaan;
use App\Support\Filament\Pages\BaseCreateRecord;

class CreateMk extends BaseCreateRecord
{
    use AssignsKurikulumTerpilihOnCreate {
        mutateFormDataBeforeCreate as assignKurikulumTerpilih;
    }
    use MenarikPilihanPemetaan;

    protected static string $resource = MkResource::class;

    /** @var list<string> */
    private array $cplBokIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->assignKurikulumTerpilih($data);
        [$data, $this->cplBokIds] = $this->tarikPilihanPemetaan($data, 'cpl_bok_ids');

        $data['state'] = 'draft';
        $data['sks'] = (int) ($data['sks_teori'] ?? 0)
            + (int) ($data['sks_praktik'] ?? 0)
            + (int) ($data['sks_lapangan'] ?? 0);

        return $data;
    }

    /**
     * MK terhubung ke CPL lewat pasangan CPL–BoK (cpl_mk). Pasangan yang dipilih
     * langsung dipetakan, dengan bobot dibagi rata hingga total 100 — sama
     * dengan pemetaan lewat kolom "kode bahan kajian" pada impor massal.
     */
    protected function afterCreate(): void
    {
        /** @var Mk $mk */
        $mk = $this->getRecord();
        $tersedia = MkResource::cplBokOptions();
        $terpilih = array_values(array_filter($this->cplBokIds, fn (string $id): bool => array_key_exists($id, $tersedia)));

        if ($terpilih === []) {
            return;
        }

        $jumlah = count($terpilih);
        $perPivot = round(100 / $jumlah, CplMk::DESIMAL_BOBOT);
        $sisa = 100.0;

        foreach ($terpilih as $indeks => $cplBokId) {
            $bobot = $indeks === $jumlah - 1 ? round($sisa, CplMk::DESIMAL_BOBOT) : $perPivot;
            $sisa -= $bobot;

            CplMk::query()->firstOrCreate(
                ['cpl_bok_id' => $cplBokId, 'mk_id' => $mk->id],
                ['bobot' => max($bobot, 0.1)],
            );
        }
    }
}
