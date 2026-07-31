<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Penilaian\Models\KomponenPenilaian;

/**
 * Menyalin Komponen Penilaian (kode, nama, bobot, evaluasi) dari satu
 * semester ke semester lain pada MK yang sama. Pemetaan Sub-CPMK-nya tidak
 * disalin mentah (subcpmk_id sumber menunjuk ke Sub-CPMK semester sumber) —
 * per pemetaan, Sub-CPMK dicari ulang berdasarkan kode pada semester tujuan
 * lewat SubcpmkAsesmenPemetaanService::cariSubcpmkUntukMk() (fungsi yang sama
 * dipakai impor asesmen biasa); yang tidak ketemu dilewati, bukan gagal total.
 */
class KomponenPenilaianSalinSemesterService
{
    /**
     * ID semester (distinct) yang punya minimal satu Komponen Penilaian
     * untuk MK ini — dasar opsi dropdown sumber pada "Import dari Semester Lain".
     *
     * @return list<string>
     */
    public function semesterIdsDenganData(string $mkId): array
    {
        return KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->distinct()
            ->pluck('semester_id')
            ->all();
    }

    /**
     * @return list<array{line: int, label: string, status: string, keterangan: string, komponen_id: string, existing_id: ?string}>
     */
    public function resolveBaris(string $sumberSemesterId, string $mkId, string $targetSemesterId): array
    {
        $sumber = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->where('semester_id', $sumberSemesterId)
            ->with('subcpmkKomponens.subcpmk')
            ->orderBy('kode')
            ->get();

        $existingByKode = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->where('semester_id', $targetSemesterId)
            ->pluck('id', 'kode');

        return $sumber->values()->map(function (KomponenPenilaian $k, int $i) use ($existingByKode, $mkId, $targetSemesterId): array {
            $duplikat = $existingByKode->has($k->kode);

            $jumlahPivot = $k->subcpmkKomponens->count();
            $jumlahBisaDipetakan = $k->subcpmkKomponens
                ->pluck('subcpmk.kode')
                ->filter()
                ->filter(fn (string $kode): bool => SubcpmkAsesmenPemetaanService::cariSubcpmkUntukMk($kode, $mkId, $targetSemesterId) !== null)
                ->count();

            $keteranganPemetaan = $jumlahPivot > 0
                ? sprintf(' %d/%d pemetaan Sub-CPMK bisa ikut disalin.', $jumlahBisaDipetakan, $jumlahPivot)
                : '';

            return [
                'line' => $i + 1,
                'label' => sprintf('%s — %s (bobot %s)', $k->kode, $k->nama, rtrim(rtrim(number_format((float) $k->bobot, 2), '0'), '.')),
                'status' => $duplikat ? 'duplikat' : 'baru',
                'keterangan' => ($duplikat
                    ? 'Kode asesmen ini sudah ada pada semester tujuan.'
                    : 'Siap disalin.').$keteranganPemetaan,
                'komponen_id' => $k->id,
                'existing_id' => $duplikat ? (string) $existingByKode->get($k->kode) : null,
            ];
        })->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{dibuat: int, diperbarui: int, dilewati: int, gagal: list<string>}
     */
    public function jalankan(array $rows, string $modeDuplikat, string $mkId, string $targetSemesterId): array
    {
        $dibuat = 0;
        $diperbarui = 0;
        $dilewati = 0;
        $gagal = [];

        foreach ($rows as $row) {
            $sumber = KomponenPenilaian::query()
                ->with('subcpmkKomponens.subcpmk')
                ->find($row['komponen_id']);

            if (! $sumber instanceof KomponenPenilaian) {
                $gagal[] = "Baris {$row['line']}: data sumber tidak ditemukan.";

                continue;
            }

            if ($row['status'] === 'duplikat' && $modeDuplikat !== 'timpa') {
                $dilewati++;

                continue;
            }

            $payload = [
                'evaluasi_id' => $sumber->evaluasi_id,
                'nama' => $sumber->nama,
                'bobot' => $sumber->bobot,
            ];

            if ($row['status'] === 'duplikat') {
                if (blank($row['existing_id'])) {
                    $gagal[] = "Baris {$row['line']}: data lama pada semester tujuan tidak ditemukan.";

                    continue;
                }

                $target = KomponenPenilaian::query()->find($row['existing_id']);

                if (! $target instanceof KomponenPenilaian) {
                    $gagal[] = "Baris {$row['line']}: data lama pada semester tujuan tidak ditemukan.";

                    continue;
                }

                $target->update($payload);
                $diperbarui++;
            } else {
                $target = KomponenPenilaian::query()->create([
                    ...$payload,
                    'mk_id' => $mkId,
                    'semester_id' => $targetSemesterId,
                    'kode' => $sumber->kode,
                ]);

                $dibuat++;
            }

            foreach ($sumber->subcpmkKomponens as $pivotSumber) {
                $kodeSubcpmk = $pivotSumber->subcpmk?->kode;

                if (blank($kodeSubcpmk)) {
                    continue;
                }

                $subcpmkTujuan = SubcpmkAsesmenPemetaanService::cariSubcpmkUntukMk($kodeSubcpmk, $mkId, $targetSemesterId);

                if ($subcpmkTujuan === null) {
                    continue;
                }

                SubcpmkAsesmenPemetaanService::petakanSubcpmk($target, $subcpmkTujuan);
            }
        }

        return compact('dibuat', 'diperbarui', 'dilewati', 'gagal');
    }
}
