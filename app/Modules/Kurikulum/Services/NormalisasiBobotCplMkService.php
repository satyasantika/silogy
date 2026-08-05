<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\CPL\Models\CplMk;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Menormalisasi bobot interaksi CPL ↔ MK milik satu baris MK, secara
 * proporsional dan dibulatkan ke N desimal (default: satuan), agar totalnya
 * tepat 100%.
 *
 * Hak edit ditentukan sepenuhnya oleh kepemilikan MK (lihat
 * CplBokAdaptasiScope::canEditCplMkCell()) — bukan per kolom CplBok.
 */
class NormalisasiBobotCplMkService
{
    /**
     * @return array{status: 'kosong'|'sudah_pas'|'dinormalisasi'|'terkunci', jumlah: int, total_sebelum: float, total_terkunci: float}
     */
    public function normalisasi(Mk $mk, string $unitId, int $desimal = 0): array
    {
        $rows = CplMk::query()->where('mk_id', $mk->id)->get();

        if ($rows->isEmpty()) {
            return ['status' => 'kosong', 'jumlah' => 0, 'total_sebelum' => 0.0, 'total_terkunci' => 0.0];
        }

        $mkEditable = CplBokAdaptasiScope::canEditCplMkCell($mk, $unitId);
        $editable = $mkEditable ? $rows : $rows->take(0);
        $locked = $mkEditable ? $rows->take(0) : $rows;

        $totalTerkunci = (float) $locked->sum('bobot');
        $total = $totalTerkunci + (float) $editable->sum('bobot');
        $target = 100.0 - $totalTerkunci;

        $bobotEditable = $editable->mapWithKeys(
            fn (CplMk $row): array => [$row->getKey() => (float) $row->bobot],
        );

        if ($editable->isNotEmpty() && BobotNormalizer::sudahSesuai($bobotEditable, $target, $desimal)) {
            return [
                'status' => 'sudah_pas',
                'jumlah' => $rows->count(),
                'total_sebelum' => $total,
                'total_terkunci' => $totalTerkunci,
            ];
        }

        if ($editable->isEmpty() || $target <= 0) {
            return [
                'status' => 'terkunci',
                'jumlah' => $editable->count(),
                'total_sebelum' => $total,
                'total_terkunci' => $totalTerkunci,
            ];
        }

        $dibulatkan = BobotNormalizer::keTarget($bobotEditable, $target, $desimal);

        DB::transaction(function () use ($editable, $dibulatkan): void {
            foreach ($editable as $row) {
                $row->update(['bobot' => $dibulatkan[$row->getKey()]]);
            }
        });

        return [
            'status' => 'dinormalisasi',
            'jumlah' => $editable->count(),
            'total_sebelum' => $total,
            'total_terkunci' => $totalTerkunci,
        ];
    }
}
