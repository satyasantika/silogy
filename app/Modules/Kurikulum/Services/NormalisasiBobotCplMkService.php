<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;
use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Menormalisasi bobot interaksi CPL ↔ MK milik satu kolom CplBok, secara
 * proporsional dan dibulatkan ke 2 desimal, agar totalnya tepat 100%.
 *
 * Berbeda dari NormalisasiBobotSubcpmkService: baris CplMk yang MK-nya
 * bukan milik unit yang sedang melihat (mis. MK adaptasi dari
 * universitas/fakultas) bersifat terkunci/read-only — redistribusi hanya
 * menyentuh baris yang benar-benar bisa diedit unit tsb, dengan target
 * efektif (100 - total bobot baris terkunci).
 */
class NormalisasiBobotCplMkService
{
    /**
     * @return array{status: 'kosong'|'sudah_pas'|'dinormalisasi'|'terkunci', jumlah: int, total_sebelum: float, total_terkunci: float}
     */
    public function normalisasi(CplBok $cplBok, string $unitId): array
    {
        $rows = CplMk::query()->where('cpl_bok_id', $cplBok->id)->with('mk')->get();

        if ($rows->isEmpty()) {
            return ['status' => 'kosong', 'jumlah' => 0, 'total_sebelum' => 0.0, 'total_terkunci' => 0.0];
        }

        $editable = $rows->filter(
            fn (CplMk $row): bool => CplBokAdaptasiScope::canEditCplMkCell($row->mk, $cplBok, $unitId),
        );
        $locked = $rows->reject(fn (CplMk $row): bool => $editable->contains($row));

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
