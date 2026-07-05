<?php

namespace App\Modules\MK\Services;

use App\Modules\MK\Models\MkUnit;

class MkUnitSalinExportService
{
    /**
     * Ekspor penawaran MK sebagai baris TSV (tab) siap tempel ke Excel.
     */
    public function exportTsv(string $academicUnitId): string
    {
        $rows = MkUnit::query()
            ->where('academic_unit_id', $academicUnitId)
            ->with('mk')
            ->get()
            ->sortBy(fn (MkUnit $mkUnit): string => mb_strtolower(trim((string) $mkUnit->mk?->nama)))
            ->values();

        return $rows
            ->map(function (MkUnit $mkUnit): string {
                $nama = trim((string) $mkUnit->mk?->nama);
                $kode = trim((string) $mkUnit->kode);
                $semester = (string) ($mkUnit->semester_ke ?? '');

                return implode("\t", [$nama, $kode, $semester]);
            })
            ->join("\n");
    }
}
