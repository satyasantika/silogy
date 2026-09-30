<?php

namespace App\Modules\MK\Services;

use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\CpmkSemester;
use Illuminate\Support\Facades\DB;

/**
 * "Pakai CPMK semester lain": MELAMPIRKAN baris CPMK yang sudah ada ke
 * semester tujuan, bukan menyalinnya. ID CPMK tetap sama, sehingga riwayat
 * capaian lintas semester tetap bisa ditelusuri lewat satu identitas.
 *
 * Melampirkan CPMK lama TIDAK butuh persetujuan Tim Kurikulum — yang butuh
 * persetujuan adalah menyusun CPMK baru untuk semester berjalan (lihat
 * GerbangPerubahanCpmk).
 */
class CpmkPakaiUlangSemesterService
{
    /**
     * @return list<string>
     */
    public function semesterIdsDenganData(string $mkId): array
    {
        /** @var list<string> $ids */
        $ids = DB::table('cpmk_semester')
            ->join('cpmk', 'cpmk.id', '=', 'cpmk_semester.cpmk_id')
            ->where('cpmk.mk_id', $mkId)
            ->distinct()
            ->pluck('cpmk_semester.semester_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        return $ids;
    }

    /**
     * @return list<array{line: int, label: string, status: string, keterangan: string, cpmk_id: string}>
     */
    public function resolveBaris(string $sumberSemesterId, string $mkId, string $targetSemesterId): array
    {
        $sumber = Cpmk::query()
            ->where('mk_id', $mkId)
            ->untukSemester($sumberSemesterId)
            ->orderBy('kode')
            ->get();

        $sudahTerlampir = Cpmk::query()
            ->where('mk_id', $mkId)
            ->untukSemester($targetSemesterId)
            ->pluck('id')
            ->flip();

        return $sumber->values()->map(function (Cpmk $cpmk, int $i) use ($sudahTerlampir): array {
            $sudah = $sudahTerlampir->has($cpmk->id);

            return [
                'line' => $i + 1,
                'label' => $cpmk->kode.' — '.str($cpmk->deskripsi)->limit(80),
                'status' => $sudah ? 'sudah_terpakai' : 'baru',
                'keterangan' => $sudah
                    ? 'CPMK ini sudah berlaku pada semester tujuan.'
                    : 'Siap dipakai ulang — ID CPMK tetap sama.',
                'cpmk_id' => (string) $cpmk->id,
            ];
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{dilampirkan: int, dilewati: int, gagal: list<string>}
     */
    public function jalankan(array $rows, string $mkId, string $targetSemesterId): array
    {
        $dilampirkan = 0;
        $dilewati = 0;
        $gagal = [];

        foreach ($rows as $row) {
            if ($row['status'] !== 'baru') {
                $dilewati++;

                continue;
            }

            $cpmk = Cpmk::query()->where('mk_id', $mkId)->find($row['cpmk_id']);

            if (! $cpmk instanceof Cpmk) {
                $gagal[] = "Baris {$row['line']}: CPMK sumber tidak ditemukan pada mata kuliah ini.";

                continue;
            }

            CpmkSemester::query()->firstOrCreate([
                'cpmk_id' => $cpmk->id,
                'semester_id' => $targetSemesterId,
            ]);

            $dilampirkan++;
        }

        return compact('dilampirkan', 'dilewati', 'gagal');
    }
}
