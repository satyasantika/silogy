<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\MK\Support\PenawaranMkScope;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Menormalisasi bobot asesmen (dikelompokkan per kode, berlaku ke semua
 * kelas dalam mata kuliah + semester yang sama) secara proporsional dan
 * dibulatkan ke bilangan bulat terdekat, agar totalnya tepat 100%.
 */
class NormalisasiBobotKomponenService
{
    /**
     * @return array{status: 'kosong'|'sudah_pas'|'dinormalisasi', jumlah_asesmen: int, total_sebelum: float}
     */
    public function normalisasi(string $mkId, string $semesterId): array
    {
        $kelasMkIds = PenawaranMkScope::kelasMkUntukMkSemester($mkId, $semesterId)->pluck('id');

        if ($kelasMkIds->isEmpty()) {
            return ['status' => 'kosong', 'jumlah_asesmen' => 0, 'total_sebelum' => 0.0];
        }

        $perKode = KomponenPenilaian::query()
            ->whereIn('kelas_mk_id', $kelasMkIds)
            ->whereNotNull('kode')
            ->get()
            ->groupBy('kode')
            ->map(fn ($rows) => (float) $rows->max('bobot'));

        $total = (float) $perKode->sum();

        if ($perKode->isEmpty() || $total <= 0) {
            return ['status' => 'kosong', 'jumlah_asesmen' => $perKode->count(), 'total_sebelum' => $total];
        }

        if (abs($total - 100) < 0.01) {
            return ['status' => 'sudah_pas', 'jumlah_asesmen' => $perKode->count(), 'total_sebelum' => $total];
        }

        $dibulatkan = BobotNormalizer::keSeratus($perKode);

        DB::transaction(function () use ($kelasMkIds, $dibulatkan): void {
            foreach ($dibulatkan as $kode => $bobotBaru) {
                KomponenPenilaian::query()
                    ->whereIn('kelas_mk_id', $kelasMkIds)
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
