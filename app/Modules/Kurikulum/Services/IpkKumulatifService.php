<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Penilaian\Services\PenilaianMatrixService;
use Illuminate\Support\Collection;

/**
 * Tab 4 — "Hasil Analisis Asesmen CPL per Mahasiswa": IPK kumulatif prodi,
 * dihitung dari SELURUH KelasMkMahasiswa yang pernah dikontrak tiap
 * mahasiswa (bukan satu MK/kelas saja seperti seluruh layanan Penilaian
 * lain di app ini).
 */
class IpkKumulatifService
{
    public function __construct(private readonly PenilaianMatrixService $matrix) {}

    /**
     * Interpretasi (di luar cakupan "IPK kumulatif prodi" yang dikonfirmasi
     * user): "Nilai Angka"/"Nilai Huruf" pada baris = rerata nilai_angka
     * mahasiswa itu HANYA dari kelas yang SUDAH dinilai (kelas yang
     * nilai_angka-nya masih null diabaikan, bukan dianggap 0) — dipakai
     * murni sebagai ringkasan tampilan. "IPK" dihitung terpisah: SKS-weighted
     * dari huruf ASLI tiap kelas yang sudah dinilai (bukan dari huruf
     * rata-rata), pembagi hanya SKS kelas yang sudah dinilai — konvensi IPK
     * standar (kelas yang belum dinilai tidak ikut menurunkan IPK).
     *
     * @return list<array{
     *     mahasiswa_id: string, nim: string, nama: string,
     *     sks_dikontrak: int, nilai_angka: float|null, nilai_huruf: string|null,
     *     bobot_huruf: float, ipk: float,
     * }>
     */
    public function rosterProdi(AcademicUnit $prodi): array
    {
        $mahasiswaList = Mahasiswa::query()
            ->where('academic_unit_id', $prodi->id)
            ->orderBy('nim')
            ->get();

        if ($mahasiswaList->isEmpty()) {
            return [];
        }

        $enrollmentsByMahasiswa = KelasMkMahasiswa::query()
            ->whereIn('mahasiswa_id', $mahasiswaList->pluck('id'))
            ->with('kelasMk.mkUnit.mk')
            ->get()
            ->groupBy('mahasiswa_id');

        return $mahasiswaList
            ->map(fn (Mahasiswa $mahasiswa): array => $this->ringkasanSatuMahasiswa(
                $mahasiswa,
                $enrollmentsByMahasiswa->get($mahasiswa->id, new Collection),
            ))
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, KelasMkMahasiswa>  $enrollments
     * @return array{
     *     mahasiswa_id: string, nim: string, nama: string,
     *     sks_dikontrak: int, nilai_angka: float|null, nilai_huruf: string|null,
     *     bobot_huruf: float, ipk: float,
     * }
     */
    private function ringkasanSatuMahasiswa(Mahasiswa $mahasiswa, Collection $enrollments): array
    {
        $sksDari = fn (KelasMkMahasiswa $row): int => $row->kelasMk->mkUnit->mk->total_sks;

        $sksDikontrak = $enrollments->sum($sksDari);

        $sudahDinilai = $enrollments->filter(fn (KelasMkMahasiswa $row): bool => $row->nilai_angka !== null);

        $nilaiAngka = $sudahDinilai->isNotEmpty() ? round((float) $sudahDinilai->avg('nilai_angka'), 2) : null;
        $nilaiHuruf = $nilaiAngka !== null ? $this->matrix->hurufDariNilaiAkhir($nilaiAngka) : null;
        $bobotHuruf = $nilaiHuruf !== null ? $this->matrix->bobotMutuDariHuruf($nilaiHuruf) : 0.0;

        $sksDinilai = $sudahDinilai->sum($sksDari);
        $mutuTerboboti = $sudahDinilai->sum(function (KelasMkMahasiswa $row) use ($sksDari): float {
            $huruf = $row->nilai_huruf ?? $this->matrix->hurufDariNilaiAkhir((float) $row->nilai_angka);

            return $sksDari($row) * $this->matrix->bobotMutuDariHuruf($huruf);
        });

        return [
            'mahasiswa_id' => $mahasiswa->id,
            'nim' => $mahasiswa->nim,
            'nama' => $mahasiswa->nama,
            'sks_dikontrak' => $sksDikontrak,
            'nilai_angka' => $nilaiAngka,
            'nilai_huruf' => $nilaiHuruf,
            'bobot_huruf' => $bobotHuruf,
            'ipk' => $sksDinilai > 0 ? round($mutuTerboboti / $sksDinilai, 2) : 0.0,
        ];
    }

    /**
     * Capaian CPL satu mahasiswa lintas SEMUA mata kuliah yang pernah
     * diambil (dipakai modal "Grafik" pada roster) — beda dari
     * EvaluasiCplService::ketercapaianCplPerKelas() yang di-scope satu kelas.
     *
     * @return list<array{cpl_kode: string, cpl_deskripsi: string, nilai_rata_rata: float}>
     */
    public function capaianCplMahasiswa(string $mahasiswaId): array
    {
        $kmmIds = KelasMkMahasiswa::query()->where('mahasiswa_id', $mahasiswaId)->pluck('id');

        if ($kmmIds->isEmpty()) {
            return [];
        }

        return HasilCplMk::query()
            ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
            ->whereNotNull('nilai_akhir')
            ->with('cpl')
            ->get()
            ->filter(fn (HasilCplMk $hasil): bool => $hasil->cpl !== null)
            ->groupBy('cpl_id')
            ->map(fn (Collection $rows): array => [
                'cpl_kode' => $rows->first()->cpl->kode,
                'cpl_deskripsi' => $rows->first()->cpl->deskripsi,
                'nilai_rata_rata' => round((float) $rows->avg('nilai_akhir'), 2),
            ])
            ->sortBy('cpl_kode')
            ->values()
            ->all();
    }
}
