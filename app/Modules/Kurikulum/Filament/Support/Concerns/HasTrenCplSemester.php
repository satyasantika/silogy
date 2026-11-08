<?php

namespace App\Modules\Kurikulum\Filament\Support\Concerns;

use App\Modules\Kurikulum\Services\AnalisisMkProdiService;
use App\Modules\Kurikulum\Services\TrenCplSemesterService;

/**
 * Data grafik "Tren Ketercapaian CPL Antarsemester" untuk halaman yang
 * bekerja atas kurikulum terpilih (Grafik CPL Pimpinan, Analisis MK).
 */
trait HasTrenCplSemester
{
    /**
     * @return array{target: int, semesters: list<array{id: string, label: string}>, cpl: list<array{cpl_id: string, kode: string, data: list<float|null>}>}|null
     */
    public function getTrenCplProperty(): ?array
    {
        $kurikulum = $this->getKurikulumProperty();

        if ($kurikulum === null) {
            return null;
        }

        return app(TrenCplSemesterService::class)->tren(
            $kurikulum,
            app(AnalisisMkProdiService::class)->mkUnitIdsUntukKurikulum($kurikulum),
        );
    }
}
