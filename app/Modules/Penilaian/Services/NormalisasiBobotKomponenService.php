<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Penilaian\Models\KomponenPenilaian;
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

        if (BobotNormalizer::sudahSesuai($perKode, 100.0, $desimal)) {
            return ['status' => 'sudah_pas', 'jumlah_asesmen' => $perKode->count(), 'total_sebelum' => $total];
        }

        $dibulatkan = BobotNormalizer::keSeratus($perKode, $desimal);

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
