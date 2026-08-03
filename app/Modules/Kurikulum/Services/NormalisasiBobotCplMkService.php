<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\CPL\Models\CplMk;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Menormalisasi bobot interaksi CPL ↔ MK milik satu baris MK, secara
 * proporsional dan dibulatkan ke 2 desimal, agar totalnya tepat 100%.
 *
 * Hak edit ditentukan sepenuhnya oleh kepemilikan MK (lihat
 * CplBokAdaptasiScope::canEditCplMkCell()) — bukan per kolom CplBok.
 * Akibatnya seluruh baris CplMk milik satu panggilan normalisasi() ini
 * SELALU seragam: bila MK bukan milik unit yang melihat (mis. MK
 * adaptasi dari universitas/fakultas), semua barisnya terkunci/read-only
 * sekaligus, tidak ada redistribusi parsial ke target tersisa seperti
 * pada NormalisasiBobotSubcpmkService.
 */
class NormalisasiBobotCplMkService
{
    /**
     * @return array{status: 'kosong'|'sudah_pas'|'dinormalisasi'|'terkunci', jumlah: int, total_sebelum: float, total_terkunci: float}
     */
    public function normalisasi(Mk $mk, string $unitId): array
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

        if (abs($total - 100.0) < 0.01) {
            return [
                'status' => 'sudah_pas',
                'jumlah' => $rows->count(),
                'total_sebelum' => $total,
                'total_terkunci' => $totalTerkunci,
            ];
        }

        $target = 100.0 - $totalTerkunci;

        if ($editable->isEmpty() || $target <= 0) {
            return [
                'status' => 'terkunci',
                'jumlah' => $editable->count(),
                'total_sebelum' => $total,
                'total_terkunci' => $totalTerkunci,
            ];
        }

        $bobotPerId = $editable->mapWithKeys(fn (CplMk $row): array => [$row->getKey() => (float) $row->bobot]);
        $dibulatkan = BobotNormalizer::keTarget($bobotPerId, $target);

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
