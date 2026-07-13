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
     * Kolom laporan Portofolio dikelompokkan per jenis penilaian (Evaluasi),
     * bukan per KomponenPenilaian — satu jenis (mis. "Tugas") bisa punya
     * beberapa asesmen sekaligus (Tugas 1, Tugas 2, dst.), jadi nilainya
     * diakumulasi lewat nilaiEvaluasiUntukMatrix().
     *
     * @param  Collection<int, KomponenPenilaian>  $komponens
     * @return list<array{id: string, label: string, bobot: float, cpl: string|null, komponen_ids: list<string>}>
     */
    public function kolomEvaluasiDariKomponens(Collection $komponens): array
    {
        return $komponens
            ->groupBy('evaluasi_id')
            ->map(function (Collection $group): array {
                /** @var KomponenPenilaian $pertama */
                $pertama = $group->first();

                $cplKodes = $group
                    ->flatMap(fn (KomponenPenilaian $komponen) => $komponen->subcpmkKomponens)
                    ->map(fn (SubcpmkKomponenPenilaian $skp): ?string => $skp->subcpmk?->mkCpmk?->cplMk?->cplBok?->cpl?->kode)
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'id' => $pertama->evaluasi_id,
                    'label' => $pertama->evaluasi?->nama ?? '—',
                    'bobot' => round((float) $group->sum(fn (KomponenPenilaian $k): float => (float) $k->bobot), 2),
                    'cpl' => $cplKodes->isNotEmpty() ? $cplKodes->implode(', ') : null,
                    'komponen_ids' => $group->pluck('id')->all(),
                ];
            })
            ->sortByDesc('bobot')
            ->values()
            ->all();
    }

    /**
     * Akumulasi nilai per kelompok jenis penilaian (rata-rata berbobot bobot
     * KomponenPenilaian di baliknya) — sel yang belum dinilai dianggap 0,
     * konsisten dengan hitungNilaiAkhirMahasiswa().
     *
     * @param  list<array{id: string, bobot: float}>  $columns  kolom asli per komponen (untuk lookup bobot)
     * @param  list<array{id: string, komponen_ids: list<string>}>  $kolomEvaluasi
     * @param  array<string, array<string, string|null>>  $nilai
     * @return array<string, array<string, float>>
     */
    public function nilaiEvaluasiUntukMatrix(array $columns, array $kolomEvaluasi, array $nilai): array
    {
        $bobotByKomponen = collect($columns)->pluck('bobot', 'id');

        $hasil = [];

        foreach ($nilai as $kmmId => $nilaiBaris) {
            $hasil[$kmmId] = [];

            foreach ($kolomEvaluasi as $kolom) {
                $totalBobot = 0.0;
                $totalNilai = 0.0;

                foreach ($kolom['komponen_ids'] as $komponenId) {
                    $bobot = (float) ($bobotByKomponen[$komponenId] ?? 0);
                    $totalBobot += $bobot;

                    $nilaiSel = $nilaiBaris[$komponenId] ?? null;
                    $totalNilai += $bobot * ($nilaiSel !== null && $nilaiSel !== '' ? (float) $nilaiSel : 0.0);
                }

                $hasil[$kmmId][$kolom['id']] = $totalBobot > 0 ? round($totalNilai / $totalBobot, 2) : 0.0;
            }
        }

        return $hasil;
    }

    /**
     * Rata-rata kelas untuk kolom "Nilai Akhir" (nilai_angka tersimpan pada
     * KelasMkMahasiswa) — mahasiswa yang belum punya nilai akhir dikecualikan
     * dari rata-rata, konsisten dengan rataRataKelas() per kolom asesmen.
     *
     * @param  list<array{nilai_angka: float|null}>  $rows
     */
    public function rataRataNilaiAkhir(array $rows): ?float
    {
        $nilaiTerisi = collect($rows)
            ->pluck('nilai_angka')
            ->filter(fn (?float $nilai): bool => $nilai !== null)
            ->values();

        return $nilaiTerisi->isNotEmpty() ? round((float) $nilaiTerisi->avg(), 2) : null;
    }

    /**
     * Nilai akhir mahasiswa = jumlah berbobot seluruh kolom asesmen (bobot
     * komponen, bukan bobot per pivot Sub-CPMK); sel yang belum dinilai
     * dianggap 0 agar nilai akhir tetap bisa dihitung begitu minimal satu
     * asesmen tersimpan.
     *
     * @param  list<array{id: string, bobot: float}>  $columns
     * @param  array<string, string|null>  $nilaiBaris
     */
    public function hitungNilaiAkhirMahasiswa(array $columns, array $nilaiBaris): float
    {
        $total = 0.0;

        foreach ($columns as $column) {
            $nilai = $nilaiBaris[$column['id']] ?? null;
            $total += ($column['bobot'] / 100) * ($nilai !== null && $nilai !== '' ? (float) $nilai : 0.0);
        }

        return round($total, 2);
    }

    /**
     * Skala huruf standar 10 tingkat (A s.d. E) — belum ada acuan skala
     * konversi lain di basis data, jadi dipakai konvensi umum perguruan
     * tinggi di Indonesia.
     */
    public function hurufDariNilaiAkhir(float $nilaiAkhir): string
    {
        return match (true) {
            $nilaiAkhir >= 85 => 'A',
            $nilaiAkhir >= 80 => 'A-',
            $nilaiAkhir >= 75 => 'B+',
            $nilaiAkhir >= 70 => 'B',
            $nilaiAkhir >= 65 => 'B-',
            $nilaiAkhir >= 60 => 'C+',
            $nilaiAkhir >= 55 => 'C',
            $nilaiAkhir >= 50 => 'C-',
            $nilaiAkhir >= 40 => 'D',
            default => 'E',
        };
    }

    /**
     * Bobot mutu standar perguruan tinggi Indonesia (skala 4.00) — dipakai
     * untuk menghitung IPK kumulatif dari huruf tiap mata kuliah.
     */
    public function bobotMutuDariHuruf(string $huruf): float
    {
        return match ($huruf) {
            'A' => 4.00,
            'A-' => 3.7,
            'B+' => 3.3,
            'B' => 3.0,
            'B-' => 2.7,
            'C+' => 2.3,
            'C' => 2.0,
            'C-' => 1.7,
            'D' => 1.0,
            default => 0.0,
        };
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
