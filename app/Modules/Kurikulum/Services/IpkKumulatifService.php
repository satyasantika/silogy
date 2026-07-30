<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Services\PenilaianMatrixService;
use Illuminate\Support\Collection;

/**
 * Tab 4 — "Hasil Analisis Asesmen CPL per Mahasiswa": IPK dan capaian CPL
 * kumulatif yang dihitung HANYA dari penawaran MK (mk_units) yang relevan
 * untuk kurikulum yang sedang dikerjakan — bukan lintas seluruh kelas prodi.
 * Untuk kurikulum fakultas/universitas, mk_unit_ids adalah rollup lintas
 * prodi turunan (lihat AnalisisMkProdiService::mkUnitIdsUntukKurikulum()).
 */
class IpkKumulatifService
{
    public function __construct(private readonly PenilaianMatrixService $matrix) {}

    /**
     * Hanya mahasiswa yang pernah mengontrak kelas pada penawaran MK
     * kurikulum ini. Interpretasi: "Nilai Angka"/"Nilai Huruf" = rerata
     * nilai_angka dari kelas kurikulum ini yang SUDAH dinilai (nilai_angka
     * null diabaikan). "IPK" = SKS-weighted dari huruf asli tiap kelas yang
     * sudah dinilai pada kurikulum yang sama; kelas kurikulum lain tidak ikut.
     *
     * @param  ?Collection<int, string>  $mkUnitIds  default: mk_units milik kurikulum ini
     * @return list<array{
     *     mahasiswa_id: string, nim: string, nama: string,
     *     sks_dikontrak: int, nilai_angka: float|null, nilai_huruf: string|null,
     *     bobot_huruf: float, ipk: float,
     * }>
     */
    public function rosterKurikulum(Kurikulum $kurikulum, ?Collection $mkUnitIds = null): array
    {
        $mkUnitIds ??= MkUnit::query()->where('kurikulum_id', $kurikulum->id)->pluck('id');

        if ($mkUnitIds->isEmpty()) {
            return [];
        }

        $enrollmentsByMahasiswa = KelasMkMahasiswa::query()
            ->whereHas('kelasMk', fn ($query) => $query->whereIn('mk_unit_id', $mkUnitIds))
            ->with(['mahasiswa', 'kelasMk.mkUnit.mk'])
            ->get()
            ->groupBy('mahasiswa_id');

        if ($enrollmentsByMahasiswa->isEmpty()) {
            return [];
        }

        return $enrollmentsByMahasiswa
            ->map(function (Collection $enrollments): ?array {
                /** @var Mahasiswa|null $mahasiswa */
                $mahasiswa = $enrollments->first()?->mahasiswa;

                return $mahasiswa !== null
                    ? $this->ringkasanSatuMahasiswa($mahasiswa, $enrollments)
                    : null;
            })
            ->filter()
            ->sortBy('nim')
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
     * @param  ?Collection<int, string>  $mkUnitIds  default: mk_units milik kurikulum ini
     * @return list<array{cpl_kode: string, cpl_deskripsi: string, nilai_rata_rata: float}>
     */
    public function capaianCplMahasiswa(string $mahasiswaId, Kurikulum $kurikulum, ?Collection $mkUnitIds = null): array
    {
        $mkUnitIds ??= MkUnit::query()->where('kurikulum_id', $kurikulum->id)->pluck('id');

        if ($mkUnitIds->isEmpty()) {
            return [];
        }

        $kmmIds = KelasMkMahasiswa::query()
            ->where('mahasiswa_id', $mahasiswaId)
            ->whereHas('kelasMk', fn ($query) => $query->whereIn('mk_unit_id', $mkUnitIds))
            ->pluck('id');

        if ($kmmIds->isEmpty()) {
            return [];
        }

        return HasilCplMk::query()
            ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
            ->whereNotNull('nilai_akhir')
            ->whereHas('mkUnit', fn ($query) => $query->whereIn('id', $mkUnitIds))
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
