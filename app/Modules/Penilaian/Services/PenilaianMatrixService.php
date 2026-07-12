<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Illuminate\Support\Collection;

/**
 * Membangun matriks nilai (kolom asesmen x baris mahasiswa) untuk satu
 * kelas MK — dipakai bersama oleh halaman Input Nilai (bisa diedit) dan
 * Portofolio (laporan, hanya baca), agar keduanya konsisten mengikuti
 * banyaknya asesmen (KomponenPenilaian) pada MK, bukan banyaknya interaksi
 * Sub-CPMK × asesmen.
 */
class PenilaianMatrixService
{
    /**
     * @return Collection<int, KomponenPenilaian>
     */
    public function komponenUntukKelas(KelasMk $kelasMk): Collection
    {
        $mkId = $kelasMk->mkUnit?->mk_id;

        return KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->where('semester_id', $kelasMk->semester_id)
            ->with(['evaluasi', 'subcpmkKomponens.subcpmk.mkCpmk.cplMk.cplBok.cpl'])
            ->orderBy('kode')
            ->get();
    }

    /**
     * @param  Collection<int, KomponenPenilaian>  $komponens
     * @return list<array{id: string, label: string, asesmen: string, subcpmk: string, evaluasi_kode: string|null, cpl: string|null, bobot: float}>
     */
    public function kolomDariKomponens(Collection $komponens): array
    {
        return $komponens
            ->map(fn (KomponenPenilaian $komponen): array => $this->kolomDariKomponen($komponen))
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: string, nim: string, nama: string, nilai_angka: float|null, nilai_huruf: string|null}>
     */
    public function barisUntukKelas(KelasMk $kelasMk, string $urutkanKolom = 'mahasiswas.nama'): array
    {
        return KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasMk->id)
            ->with('mahasiswa')
            ->join('mahasiswas', 'mahasiswas.id', '=', 'kelas_mk_mahasiswa.mahasiswa_id')
            ->orderBy($urutkanKolom)
            ->select('kelas_mk_mahasiswa.*')
            ->get()
            ->map(fn (KelasMkMahasiswa $kmm): array => [
                'id' => $kmm->id,
                'nim' => $kmm->mahasiswa?->nim ?? '—',
                'nama' => $kmm->mahasiswa?->nama ?? '—',
                'nilai_angka' => $kmm->nilai_angka !== null ? (float) $kmm->nilai_angka : null,
                'nilai_huruf' => $kmm->nilai_huruf,
            ])
            ->values()
            ->all();
    }

    /**
     * Kolom matriks mengikuti banyaknya asesmen (KomponenPenilaian) pada MK,
     * bukan banyaknya interaksi Sub-CPMK × asesmen — satu asesmen bisa
     * dipetakan ke beberapa Sub-CPMK sekaligus, jadi nilai untuk satu kolom
     * asesmen disebar (fan-out) ke seluruh pivot Sub-CPMK di baliknya.
     *
     * @param  Collection<int, KomponenPenilaian>  $komponens
     * @return array<string, list<string>>
     */
    public function pivotIdsByKomponen(Collection $komponens): array
    {
        return $komponens
            ->mapWithKeys(fn (KomponenPenilaian $komponen): array => [
                $komponen->id => $komponen->subcpmkKomponens->pluck('id')->all(),
            ])
            ->all();
    }

    /**
     * @param  list<array{id: string}>  $rows
     * @param  array<string, list<string>>  $pivotIdsByKomponen
     * @return array<string, array<string, string|null>>
     */
    public function nilaiUntukMatrix(array $rows, array $pivotIdsByKomponen): array
    {
        $nilai = [];

        foreach ($rows as $row) {
            $nilai[$row['id']] = [];

            foreach (array_keys($pivotIdsByKomponen) as $komponenId) {
                $nilai[$row['id']][$komponenId] = null;
            }
        }

        $allPivotIds = collect($pivotIdsByKomponen)->flatten()->values();
        $kmmIds = collect($rows)->pluck('id');

        if ($allPivotIds->isEmpty() || $kmmIds->isEmpty()) {
            return $nilai;
        }

        $existing = NilaiMahasiswa::query()
            ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
            ->whereIn('subcpmk_komponenpenilaian_id', $allPivotIds)
            ->get()
            ->groupBy('kelas_mk_mahasiswa_id');

        foreach ($rows as $row) {
            foreach ($pivotIdsByKomponen as $komponenId => $pivotIds) {
                $nilaiRow = $existing
                    ->get($row['id'])
                    ?->first(fn (NilaiMahasiswa $n): bool => in_array(
                        $n->subcpmk_komponenpenilaian_id,
                        $pivotIds,
                        true,
                    ));

                $nilai[$row['id']][$komponenId] = $nilaiRow?->nilai !== null
                    ? (string) $nilaiRow->nilai
                    : null;
            }
        }

        return $nilai;
    }

    /**
     * @param  list<array{id: string}>  $columns
     * @param  list<array{id: string}>  $rows
     * @param  array<string, array<string, string|null>>  $nilai
     * @return array<string, float|null>
     */
    public function rataRataKelas(array $columns, array $rows, array $nilai): array
    {
        $rataRata = [];

        foreach ($columns as $column) {
            $nilaiTerisi = [];

            foreach ($rows as $row) {
                $value = $nilai[$row['id']][$column['id']] ?? null;

                if ($value !== null && $value !== '') {
                    $nilaiTerisi[] = (float) $value;
                }
            }

            $rataRata[$column['id']] = $nilaiTerisi !== []
                ? round(array_sum($nilaiTerisi) / count($nilaiTerisi), 2)
                : null;
        }

        return $rataRata;
    }

    /**
     * Warna badge nilai huruf (mis. A/A-, B+/B, C, D/E) untuk ditampilkan
     * berdampingan dengan nilai akhir mahasiswa pada baris tabel.
     *
     * @return array{bg: string, fg: string}
     */
    public function warnaNilaiHuruf(?string $huruf): array
    {
        return match (true) {
            $huruf === null || $huruf === '' => ['bg' => 'rgba(128,128,128,.15)', 'fg' => '#6b7280'],
            str_starts_with($huruf, 'A') => ['bg' => '#dcfce7', 'fg' => '#166534'],
            str_starts_with($huruf, 'B') => ['bg' => '#dbeafe', 'fg' => '#1d4ed8'],
            str_starts_with($huruf, 'C') => ['bg' => '#fef3c7', 'fg' => '#92400e'],
            default => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
        };
    }

    /**
     * @return array{id: string, label: string, asesmen: string, subcpmk: string, evaluasi_kode: string|null, cpl: string|null, bobot: float}
     */
    protected function kolomDariKomponen(KomponenPenilaian $komponen): array
    {
        $subcpmkKodes = $komponen->subcpmkKomponens
            ->pluck('subcpmk.kode')
            ->filter()
            ->unique()
            ->values();

        $cplKodes = $komponen->subcpmkKomponens
            ->map(fn (SubcpmkKomponenPenilaian $skp): ?string => $skp->subcpmk?->mkCpmk?->cplMk?->cplBok?->cpl?->kode)
            ->filter()
            ->unique()
            ->values();

        return [
            'id' => $komponen->id,
            'label' => $komponen->kode,
            'asesmen' => $komponen->kode,
            'subcpmk' => $subcpmkKodes->isNotEmpty() ? $subcpmkKodes->implode(', ') : '—',
            'evaluasi_kode' => $komponen->evaluasi?->kode,
            'cpl' => $cplKodes->isNotEmpty() ? $cplKodes->implode(', ') : null,
            'bobot' => round((float) $komponen->bobot, 2),
        ];
    }
}
