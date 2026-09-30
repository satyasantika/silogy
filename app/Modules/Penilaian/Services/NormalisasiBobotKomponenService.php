<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Menormalisasi bobot asesmen pada satu mata kuliah + semester secara
 * proporsional dan dibulatkan ke N desimal (default: satuan), agar totalnya
 * tepat 100%.
 */
class NormalisasiBobotKomponenService
{
    /**
     * @return array{status: 'kosong'|'sudah_pas'|'dinormalisasi', jumlah_asesmen: int, total_sebelum: float}
     */
    public function normalisasi(string $mkId, string $semesterId, int $desimal = 0): array
    {
        // Bobot dibaca dari pivot semester, bukan dari baris Asesmen: satu
        // Asesmen yang sama boleh berbobot beda di semester yang berbeda.
        $komponens = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->untukSemester($semesterId)
            ->whereNotNull('kode')
            ->get();

        $perKode = $komponens
            ->keyBy('kode')
            ->map(fn (KomponenPenilaian $komponen): float => $komponen->bobotUntukSemester($semesterId));

        $total = (float) $perKode->sum();

        if ($perKode->isEmpty() || $total <= 0) {
            return ['status' => 'kosong', 'jumlah_asesmen' => $perKode->count(), 'total_sebelum' => $total];
        }

        if (BobotNormalizer::sudahSesuai($perKode, 100.0, $desimal)) {
            return ['status' => 'sudah_pas', 'jumlah_asesmen' => $perKode->count(), 'total_sebelum' => $total];
        }

        $dibulatkan = BobotNormalizer::keSeratus($perKode, $desimal);

        DB::transaction(function () use ($komponens, $semesterId, $dibulatkan): void {
            foreach ($komponens as $komponen) {
                $bobotBaru = $dibulatkan[$komponen->kode] ?? null;

                if ($bobotBaru === null) {
                    continue;
                }

                KomponenPenilaianSemester::query()
                    ->where('komponen_penilaian_id', $komponen->id)
                    ->where('semester_id', $semesterId)
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
