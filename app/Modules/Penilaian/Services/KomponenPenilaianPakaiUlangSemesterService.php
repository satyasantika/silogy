<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\MK\Support\KaskadeReuseSemester;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Illuminate\Support\Facades\DB;

/**
 * "Pakai tagihan (asesmen) semester lain": MELAMPIRKAN baris Asesmen yang
 * sudah ada ke semester tujuan beserta bobotnya, lalu menyalin pemetaannya
 * ke Sub-CPMK untuk semester itu.
 *
 * Perhatikan yang HILANG dibanding KomponenPenilaianSalinSemesterService
 * lama: pencarian ulang Sub-CPMK berdasarkan kode. Dulu pemetaan tidak bisa
 * disalin mentah karena subcpmk_id sumber menunjuk baris milik semester
 * sumber; kini Sub-CPMK yang sama dipakai ulang lintas semester, jadi
 * pemetaan cukup menunjuk subcpmk_id yang sama dan hanya semester_id-nya
 * yang berbeda. Satu sumber bug halus lenyap.
 */
class KomponenPenilaianPakaiUlangSemesterService
{
    /**
     * @return list<string>
     */
    public function semesterIdsDenganData(string $mkId): array
    {
        /** @var list<string> $ids */
        $ids = DB::table('komponen_penilaian_semester')
            ->join('komponen_penilaian', 'komponen_penilaian.id', '=', 'komponen_penilaian_semester.komponen_penilaian_id')
            ->where('komponen_penilaian.mk_id', $mkId)
            ->distinct()
            ->pluck('komponen_penilaian_semester.semester_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        return $ids;
    }

    /**
     * Asesmen hanya ditawarkan bila SELURUH Sub-CPMK yang diukurnya di
     * semester sumber sudah berlaku di semester tujuan. Asesmen yang
     * menyentuh Sub-CPMK baru akan menghasilkan pemetaan menggantung, jadi
     * ditandai terblokir dengan menyebut Sub-CPMK mana yang belum ada.
     *
     * @return list<array{line: int, label: string, status: string, keterangan: string, komponen_id: string}>
     */
    public function resolveBaris(string $sumberSemesterId, string $mkId, string $targetSemesterId): array
    {
        $sumber = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->untukSemester($sumberSemesterId)
            ->orderBy('kode')
            ->get();

        $sudahTerlampir = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->untukSemester($targetSemesterId)
            ->pluck('id')
            ->flip();

        return $sumber->values()->map(
            function (KomponenPenilaian $komponen, int $i) use ($sudahTerlampir, $sumberSemesterId, $targetSemesterId): array {
                $bobot = $komponen->bobotUntukSemester($sumberSemesterId);
                $label = sprintf(
                    '%s — %s (bobot %s)',
                    $komponen->kode,
                    $komponen->nama,
                    rtrim(rtrim(number_format($bobot, 2, '.', ''), '0'), '.'),
                );

                if ($sudahTerlampir->has($komponen->id)) {
                    return $this->baris($i, $label, 'sudah_terpakai', 'Asesmen ini sudah berlaku pada semester tujuan.', $komponen);
                }

                $belumSiap = KaskadeReuseSemester::subcpmkBelumSiapUntukAsesmen(
                    (string) $komponen->id,
                    $sumberSemesterId,
                    $targetSemesterId,
                );

                if ($belumSiap !== []) {
                    return $this->baris(
                        $i,
                        $label,
                        'terblokir',
                        'Sub-CPMK '.implode(', ', $belumSiap).' belum berlaku pada semester tujuan — asesmen ini harus baru.',
                        $komponen,
                    );
                }

                return $this->baris($i, $label, 'baru', 'Siap dipakai ulang beserta bobot dan pemetaan Sub-CPMK-nya.', $komponen);
            },
        )->all();
    }

    /**
     * @return array{line: int, label: string, status: string, keterangan: string, komponen_id: string}
     */
    private function baris(int $index, string $label, string $status, string $keterangan, KomponenPenilaian $komponen): array
    {
        return [
            'line' => $index + 1,
            'label' => $label,
            'status' => $status,
            'keterangan' => $keterangan,
            'komponen_id' => (string) $komponen->id,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{dilampirkan: int, dilewati: int, gagal: list<string>}
     */
    public function jalankan(array $rows, string $mkId, string $sumberSemesterId, string $targetSemesterId): array
    {
        $dilampirkan = 0;
        $dilewati = 0;
        $gagal = [];

        foreach ($rows as $row) {
            if ($row['status'] !== 'baru') {
                $dilewati++;

                continue;
            }

            $komponen = KomponenPenilaian::query()->where('mk_id', $mkId)->find($row['komponen_id']);

            if (! $komponen instanceof KomponenPenilaian) {
                $gagal[] = "Baris {$row['line']}: asesmen sumber tidak ditemukan pada mata kuliah ini.";

                continue;
            }

            KomponenPenilaianSemester::query()->firstOrCreate(
                [
                    'komponen_penilaian_id' => $komponen->id,
                    'semester_id' => $targetSemesterId,
                ],
                ['bobot' => $komponen->bobotUntukSemester($sumberSemesterId)],
            );

            $this->bawaPemetaan($komponen, $sumberSemesterId, $targetSemesterId);

            $dilampirkan++;
        }

        return compact('dilampirkan', 'dilewati', 'gagal');
    }

    /**
     * Pemetaan Sub-CPMK ↔ Asesmen dibuat ulang untuk semester tujuan dengan
     * subcpmk_id dan komponen_penilaian_id yang SAMA — hanya semester_id dan
     * bobotnya yang milik semester tujuan.
     */
    private function bawaPemetaan(KomponenPenilaian $komponen, string $sumberSemesterId, string $targetSemesterId): void
    {
        $pemetaanSumber = SubcpmkKomponenPenilaian::query()
            ->where('komponen_penilaian_id', $komponen->id)
            ->where('semester_id', $sumberSemesterId)
            ->get();

        foreach ($pemetaanSumber as $pivot) {
            SubcpmkKomponenPenilaian::query()->firstOrCreate(
                [
                    'komponen_penilaian_id' => $komponen->id,
                    'subcpmk_id' => $pivot->subcpmk_id,
                    'semester_id' => $targetSemesterId,
                ],
                ['bobot' => $pivot->bobot],
            );
        }
    }
}
