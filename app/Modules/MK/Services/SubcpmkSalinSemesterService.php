<?php

namespace App\Modules\MK\Services;

use App\Modules\MK\Models\Subcpmk;

/**
 * Menyalin Sub-CPMK dari satu semester ke semester lain pada MK yang sama.
 * mk_cpmk_id tidak terikat semester (bagian kurikulum MK), jadi baris baru
 * memakai ulang mk_cpmk_id sumber apa adanya. bobot sengaja tidak disalin —
 * dihitung otomatis dari interaksi Sub-CPMK dengan Asesmen di semester tujuan.
 */
class SubcpmkSalinSemesterService
{
    /**
     * ID semester (distinct) yang punya minimal satu Sub-CPMK untuk MK ini —
     * dasar opsi dropdown sumber pada "Import dari Semester Lain".
     *
     * @return list<string>
     */
    public function semesterIdsDenganData(string $mkId): array
    {
        return Subcpmk::query()
            ->whereHas('mkCpmk.cpmk', fn ($query) => $query->where('mk_id', $mkId))
            ->whereNotNull('semester_id')
            ->distinct()
            ->pluck('semester_id')
            ->all();
    }

    /**
     * @return list<array{line: int, label: string, status: string, keterangan: string, subcpmk_id: string, existing_id: ?string, kode: string}>
     */
    public function resolveBaris(string $sumberSemesterId, string $mkId, string $targetSemesterId): array
    {
        $sumber = Subcpmk::query()
            ->where('semester_id', $sumberSemesterId)
            ->whereHas('mkCpmk.cpmk', fn ($query) => $query->where('mk_id', $mkId))
            ->orderBy('kode')
            ->get();

        $existingByKode = Subcpmk::query()
            ->where('semester_id', $targetSemesterId)
            ->whereHas('mkCpmk.cpmk', fn ($query) => $query->where('mk_id', $mkId))
            ->pluck('id', 'kode');

        return $sumber->values()->map(function (Subcpmk $s, int $i) use ($existingByKode): array {
            $duplikat = $existingByKode->has($s->kode);

            return [
                'line' => $i + 1,
                'label' => $s->kode.' — '.str($s->deskripsi)->limit(80),
                'status' => $duplikat ? 'duplikat' : 'baru',
                'keterangan' => $duplikat
                    ? 'Kode Sub-CPMK ini sudah ada pada semester tujuan.'
                    : 'Siap disalin.',
                'subcpmk_id' => $s->id,
                'existing_id' => $duplikat ? (string) $existingByKode->get($s->kode) : null,
                'kode' => $s->kode,
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
            $sumber = Subcpmk::query()->find($row['subcpmk_id']);

            if (! $sumber instanceof Subcpmk) {
                $gagal[] = "Baris {$row['line']}: data sumber tidak ditemukan.";

                continue;
            }

            $payload = [
                'mk_cpmk_id' => $sumber->mk_cpmk_id,
                'deskripsi' => $sumber->deskripsi,
                'indikator' => $sumber->indikator,
                'evaluasi' => $sumber->evaluasi,
                'bloom_kognitif' => $sumber->bloom_kognitif,
                'bloom_afektif' => $sumber->bloom_afektif,
                'bloom_psikomotorik' => $sumber->bloom_psikomotorik,
            ];

            if ($row['status'] === 'duplikat') {
                if ($modeDuplikat !== 'timpa') {
                    $dilewati++;

                    continue;
                }

                if (blank($row['existing_id'])) {
                    $gagal[] = "Baris {$row['line']}: data lama pada semester tujuan tidak ditemukan.";

                    continue;
                }

                Subcpmk::query()->whereKey($row['existing_id'])->update($payload);
                $diperbarui++;

                continue;
            }

            Subcpmk::query()->create([
                ...$payload,
                'semester_id' => $targetSemesterId,
                'kode' => $sumber->kode,
            ]);

            $dibuat++;
        }

        return compact('dibuat', 'diperbarui', 'dilewati', 'gagal');
    }
}
