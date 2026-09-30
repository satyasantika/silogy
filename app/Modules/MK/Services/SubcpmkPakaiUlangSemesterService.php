<?php

namespace App\Modules\MK\Services;

use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Models\SubcpmkSemester;
use App\Modules\MK\Support\KaskadeReuseSemester;
use Illuminate\Support\Facades\DB;

/**
 * "Pakai Sub-CPMK semester lain": MELAMPIRKAN baris Sub-CPMK yang sudah ada
 * ke semester tujuan, bukan menyalinnya. Menggantikan
 * SubcpmkSalinSemesterService yang dulu membuat ID baru berisi kode lama —
 * persis perilaku yang membuat satu Sub-CPMK tersebar sebagai banyak
 * identitas dan memutus jejak capaiannya antar semester.
 *
 * bobot sengaja tidak ikut dibawa: ia turunan dari interaksi Sub-CPMK dengan
 * Asesmen di semester tujuan, dan dihitung ulang di sana.
 */
class SubcpmkPakaiUlangSemesterService
{
    /**
     * @return list<string>
     */
    public function semesterIdsDenganData(string $mkId): array
    {
        /** @var list<string> $ids */
        $ids = DB::table('subcpmk_semester')
            ->join('subcpmk', 'subcpmk.id', '=', 'subcpmk_semester.subcpmk_id')
            ->join('mk_cpmk', 'mk_cpmk.id', '=', 'subcpmk.mk_cpmk_id')
            ->join('cpmk', 'cpmk.id', '=', 'mk_cpmk.cpmk_id')
            ->where('cpmk.mk_id', $mkId)
            ->distinct()
            ->pluck('subcpmk_semester.semester_id')
            ->map(fn ($id): string => (string) $id)
            ->all();

        return $ids;
    }

    /**
     * Sub-CPMK hanya boleh dipakai ulang bila CPMK induknya juga dipakai
     * ulang di semester tujuan. CPMK yang baru disusun untuk semester ini
     * mengukur hal yang berbeda, jadi turunannya wajib baru — inilah
     * granularitas per-CPMK: yang terhalang hanya subtree CPMK baru, bukan
     * seluruh daftar.
     *
     * @return list<array{line: int, label: string, status: string, keterangan: string, subcpmk_id: string}>
     */
    public function resolveBaris(string $sumberSemesterId, string $mkId, string $targetSemesterId): array
    {
        $sumber = Subcpmk::query()
            ->untukSemester($sumberSemesterId)
            ->whereHas('mkCpmk.cpmk', fn ($query) => $query->where('mk_id', $mkId))
            ->with('mkCpmk.cpmk')
            ->get()
            ->sortBy(fn (Subcpmk $s): string => (string) ($s->mkCpmk?->cpmk?->kode).'/'.$s->kode);

        $sudahTerlampir = Subcpmk::query()
            ->untukSemester($targetSemesterId)
            ->whereHas('mkCpmk.cpmk', fn ($query) => $query->where('mk_id', $mkId))
            ->pluck('id')
            ->flip();

        $cpmkBolehReuse = KaskadeReuseSemester::cpmkIdsBolehReuseSubtree($mkId, $targetSemesterId)->flip();

        return $sumber->values()->map(function (Subcpmk $s, int $i) use ($sudahTerlampir, $cpmkBolehReuse): array {
            $cpmk = $s->mkCpmk?->cpmk;
            $kodeCpmk = $cpmk === null ? '—' : (string) $cpmk->kode;

            // Label diawali kode CPMK supaya pilihan parsial tetap terbaca
            // per-CPMK; CheckboxList Filament tidak punya pengelompokan.
            $label = sprintf('%s · %s — %s', $kodeCpmk, $s->kode, str($s->deskripsi)->limit(70));

            if ($sudahTerlampir->has($s->id)) {
                return $this->baris($i, $label, 'sudah_terpakai', 'Sub-CPMK ini sudah berlaku pada semester tujuan.', $s);
            }

            if ($cpmk === null || ! $cpmkBolehReuse->has((string) $cpmk->id)) {
                return $this->baris(
                    $i,
                    $label,
                    'terblokir',
                    "CPMK {$kodeCpmk} baru disusun untuk semester ini — Sub-CPMK-nya harus baru.",
                    $s,
                );
            }

            return $this->baris($i, $label, 'baru', 'Siap dipakai ulang — ID Sub-CPMK tetap sama.', $s);
        })->all();
    }

    /**
     * @return array{line: int, label: string, status: string, keterangan: string, subcpmk_id: string}
     */
    private function baris(int $index, string $label, string $status, string $keterangan, Subcpmk $subcpmk): array
    {
        return [
            'line' => $index + 1,
            'label' => $label,
            'status' => $status,
            'keterangan' => $keterangan,
            'subcpmk_id' => (string) $subcpmk->id,
        ];
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

            $subcpmk = Subcpmk::query()
                ->whereHas('mkCpmk.cpmk', fn ($query) => $query->where('mk_id', $mkId))
                ->find($row['subcpmk_id']);

            if (! $subcpmk instanceof Subcpmk) {
                $gagal[] = "Baris {$row['line']}: Sub-CPMK sumber tidak ditemukan pada mata kuliah ini.";

                continue;
            }

            SubcpmkSemester::query()->firstOrCreate(
                [
                    'subcpmk_id' => $subcpmk->id,
                    'semester_id' => $targetSemesterId,
                ],
                ['bobot' => null],
            );

            $dilampirkan++;
        }

        return compact('dilampirkan', 'dilewati', 'gagal');
    }
}
