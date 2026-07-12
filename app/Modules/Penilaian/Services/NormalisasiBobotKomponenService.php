<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Menormalisasi bobot asesmen pada satu mata kuliah + semester secara
 * proporsional dan dibulatkan ke bilangan bulat terdekat, agar totalnya
 * tepat 100%.
 */
class NormalisasiBobotKomponenService
{
    /**
     * @return array{status: 'kosong'|'sudah_pas'|'dinormalisasi', jumlah_asesmen: int, total_sebelum: float}
     */
    public function normalisasi(string $mkId, string $semesterId): array
    {
        if (PenawaranMkScope::kelasMkUntukMkSemester($mkId, $semesterId)->isEmpty()) {
            return ['status' => 'kosong', 'jumlah_asesmen' => 0, 'total_sebelum' => 0.0];
        }

        $perKode = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->where('semester_id', $semesterId)
            ->whereNotNull('kode')
            ->get()
            ->keyBy('kode')
            ->map(fn (KomponenPenilaian $komponen): float => (float) $komponen->bobot);

        $total = (float) $perKode->sum();

        if ($perKode->isEmpty() || $total <= 0) {
            return ['status' => 'kosong', 'jumlah_asesmen' => $perKode->count(), 'total_sebelum' => $total];
        }

        if (abs($total - 100) < 0.01) {
            return ['status' => 'sudah_pas', 'jumlah_asesmen' => $perKode->count(), 'total_sebelum' => $total];
        }

        $dibulatkan = BobotNormalizer::keSeratus($perKode);

        DB::transaction(function () use ($mkId, $semesterId, $dibulatkan): void {
            foreach ($dibulatkan as $kode => $bobotBaru) {
                KomponenPenilaian::query()
                    ->where('mk_id', $mkId)
                    ->where('semester_id', $semesterId)
                    ->where('kode', $kode)
                    ->update(['bobot' => $bobotBaru]);
            }
        });

        return [
            'status' => 'dinormalisasi',
            'jumlah_asesmen' => $perKode->count(),
            'total_sebelum' => $total,
        ];
    }
}
