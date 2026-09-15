<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Facades\DB;

/**
 * Menormalisasi bobot interaksi Sub-CPMK ↔ Asesmen milik satu Asesmen PADA
 * SATU SEMESTER, secara proporsional dan dibulatkan ke N desimal (default:
 * satuan), agar totalnya tepat sama dengan bobot Asesmen itu di semester
 * tersebut. Semester wajib disebut karena satu Asesmen kini boleh dipakai di
 * beberapa semester dengan bobot dan pemetaan yang berbeda.
 */
class NormalisasiBobotSubcpmkService
{
    /**
     * @return array{status: 'kosong'|'sudah_pas'|'dinormalisasi', jumlah: int, total_sebelum: float}
     */
    public function normalisasi(KomponenPenilaian $komponen, string $semesterId, int $desimal = 0): array
    {
        $target = $komponen->bobotUntukSemester($semesterId);

        $rows = $komponen->subcpmkKomponens()->where('semester_id', $semesterId)->get();

        $total = (float) $rows->sum('bobot');

        if ($rows->isEmpty() || $total <= 0 || $target <= 0) {
            return ['status' => 'kosong', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
        }

        $bobotPerId = $rows->mapWithKeys(fn ($row) => [$row->getKey() => (float) $row->bobot]);

        if (BobotNormalizer::sudahSesuai($bobotPerId, $target, $desimal)) {
            return ['status' => 'sudah_pas', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
        }

        $dibulatkan = BobotNormalizer::keTarget($bobotPerId, $target, $desimal);

        DB::transaction(function () use ($rows, $dibulatkan): void {
            foreach ($rows as $row) {
                $row->update(['bobot' => $dibulatkan[$row->getKey()]]);
            }
        });

        return ['status' => 'dinormalisasi', 'jumlah' => $rows->count(), 'total_sebelum' => $total];
    }
}
