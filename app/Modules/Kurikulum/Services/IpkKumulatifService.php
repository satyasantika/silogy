<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Penilaian\Services\PenilaianMatrixService;
use Illuminate\Support\Collection;

/**
 * Tab 4 — "Hasil Analisis Asesmen CPL per Mahasiswa": IPK dan capaian CPL
 * kumulatif yang dihitung HANYA dari penawaran MK (mk_units) milik kurikulum
 * yang sedang dikerjakan — bukan lintas seluruh kelas prodi.
 */
class IpkKumulatifService
{
    public function __construct(private readonly PenilaianMatrixService $matrix) {}

    /**
     * Interpretasi: "Nilai Angka"/"Nilai Huruf" = rerata nilai_angka mahasiswa
     * HANYA dari kelas kurikulum ini yang SUDAH dinilai (nilai_angka null
     * diabaikan). "IPK" = SKS-weighted dari huruf asli tiap kelas yang sudah
     * dinilai pada kurikulum yang sama; kelas kurikulum lain tidak ikut.
     *
     * @return list<array{
     *     mahasiswa_id: string, nim: string, nama: string,
     *     sks_dikontrak: int, nilai_angka: float|null, nilai_huruf: string|null,
     *     bobot_huruf: float, ipk: float,
     * }>
     */
    public function rosterKurikulum(Kurikulum $kurikulum): array
    {
        $mahasiswaList = Mahasiswa::query()
            ->where('academic_unit_id', $kurikulum->academic_unit_id)
            ->orderBy('nim')
            ->get();

        if ($mahasiswaList->isEmpty()) {
            return [];
        }

        $enrollmentsByMahasiswa = KelasMkMahasiswa::query()
            ->whereIn('mahasiswa_id', $mahasiswaList->pluck('id'))
            ->whereHas('kelasMk.mkUnit', fn ($query) => $query->where('kurikulum_id', $kurikulum->id))
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
     * Capaian CPL satu mahasiswa pada kurikulum yang dikerjakan — hanya
     * hasil_cpl_mk dari penawaran MK kurikulum itu (dan CPL kurikulum itu).
     * Dipakai modal "Grafik" pada roster.
     *
     * @return list<array{cpl_kode: string, cpl_deskripsi: string, nilai_rata_rata: float}>
     */
    public function capaianCplMahasiswa(string $mahasiswaId, Kurikulum $kurikulum): array
    {
        $kmmIds = KelasMkMahasiswa::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->whereHas('kelasMk.mkUnit', fn ($query) => $query->where('kurikulum_id', $kurikulum->id))
            ->pluck('id');

        if ($kmmIds->isEmpty()) {
            return [];
        }

        return HasilCplMk::query()
            ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
            ->whereNotNull('nilai_akhir')
            ->whereHas('mkUnit', fn ($query) => $query->where('kurikulum_id', $kurikulum->id))
            ->whereHas('cpl', fn ($query) => $query->where('kurikulum_id', $kurikulum->id))
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
