<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\CPL\Models\CplMk;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Services\EvaluasiCplService;
use Illuminate\Support\Collection;

/**
 * Data prodi-wide untuk halaman "Analisis MK Prodi" — berbeda dari seluruh
 * layanan Penilaian lain di app ini (yang selalu per satu KelasMk), service
 * ini melihat SATU KURIKULUM (satu prodi) sekaligus: semua CPL yang
 * dibebankan pada prodi itu dan semua MK yang membebaninya.
 */
class AnalisisMkProdiService
{
    /**
     * Tab 1 — "Pemetaan Rencana Asesmen CPL": murni data kurikulum
     * (CplMk.bobot), tidak bergantung pada nilai mahasiswa sama sekali.
     *
     * @return list<array{
     *     cpl_id: string,
     *     cpl_kode: string,
     *     cpl_deskripsi: string,
     *     mk_rows: list<array{mk_id: string, nama: string, kode: string, sks: int, kontribusi: float}>,
     * }>
     */
    public function pemetaanCplMk(Kurikulum $kurikulum): array
    {
        // Tidak pakai Kurikulum::cplMks() — whereColumn() di dalamnya
        // mengasumsikan tabel "kurikulum" sudah ter-join di query luar
        // (subquery-nya gagal bila diakses langsung dari instance model).
        $cplMks = CplMk::query()
            ->whereHas('cplBok.cpl', fn ($query) => $query->where('kurikulum_id', $kurikulum->id))
            ->with(['cplBok.cpl', 'mk'])
            ->get()
            ->filter(fn (CplMk $pivot): bool => $pivot->cplBok?->cpl !== null && $pivot->mk !== null);

        if ($cplMks->isEmpty()) {
            return [];
        }

        $kodeMkUnitByMkId = $this->kodeMkUnitByMkId($kurikulum, $cplMks->pluck('mk_id')->unique());

        return $cplMks
            ->groupBy(fn (CplMk $pivot): string => $pivot->cplBok->cpl->kode)
            ->map(function (Collection $pivots, string $cplKode) use ($kodeMkUnitByMkId): array {
                $cpl = $pivots->first()->cplBok->cpl;

                $mkRows = $pivots
                    ->groupBy('mk_id')
                    ->map(function (Collection $samaMk) use ($kodeMkUnitByMkId): array {
                        $mk = $samaMk->first()->mk;

                        return [
                            'mk_id' => $mk->id,
                            'nama' => $mk->nama,
                            'kode' => $kodeMkUnitByMkId[$mk->id] ?? '—',
                            'sks' => $mk->total_sks,
                            'kontribusi' => round((float) $samaMk->sum('bobot'), 2),
                        ];
                    })
                    ->sortBy('nama')
                    ->values()
                    ->all();

                return [
                    'cpl_id' => $cpl->id,
                    'cpl_kode' => $cplKode,
                    'cpl_deskripsi' => $cpl->deskripsi,
                    'mk_rows' => $mkRows,
                ];
            })
            ->sortBy('cpl_kode')
            ->values()
            ->all();
    }

    /**
     * Memicu ulang kalkulasi CPL/CPMK/Sub-CPMK secara SINKRON untuk semua
     * KelasMk pada prodi kurikulum ini — queue 'cpl-calculation' terkonfirmasi
     * mati di environment ini, jadi hasil_cpl_mk bisa basi untuk kelas yang
     * belum pernah dibuka lewat /penilaian/input-nilai. Dipanggil sebelum
     * hasilAnalisisPerAngkatan()/radarPerCpl() dibaca.
     *
     * Catatan performa: ini menghitung ulang SEMUA kelas prodi (bukan satu
     * kelas seperti EvaluasiCplService biasa dipakai) — bisa lambat untuk
     * prodi besar, diterima untuk saat ini (lihat plan).
     */
    public function sinkronkanKalkulasiProdi(Kurikulum $kurikulum): void
    {
        $mkUnitIds = MkUnit::query()
            ->where('kurikulum_id', $kurikulum->id)
            ->pluck('id');

        if ($mkUnitIds->isEmpty()) {
            return;
        }

        $evaluasiCpl = app(EvaluasiCplService::class);

        KelasMk::query()
            ->whereIn('mk_unit_id', $mkUnitIds)
            ->each(fn (KelasMk $kelas) => $evaluasiCpl->jalankanKalkulasiSinkron($kelas));
    }

    /**
     * Tab 2 — "Hasil Analisis Asesmen CPL": sama seperti tab 1, ditambah
     * rerata nilai PER ANGKATAN mahasiswa (lintas semester manapun MK itu
     * pernah ditawarkan) dan ketercapaian CPL all-time.
     *
     * @return array{
     *     angkatan_list: list<string>,
     *     pemetaan: list<array{
     *         cpl_id: string,
     *         cpl_kode: string,
     *         cpl_deskripsi: string,
     *         ketercapaian: array{rata_rata: float|null, jumlah_mahasiswa: int, persentase_tercapai: float|null, tercapai: bool}|null,
     *         mk_rows: list<array{
     *             mk_id: string, nama: string, kode: string, sks: int, kontribusi: float,
     *             per_angkatan: array<string, array{rata_rata: float|null, n: int}>,
     *             rata_rata_keseluruhan: float|null,
     *         }>,
     *     }>,
     * }
     */
    public function hasilAnalisisPerAngkatan(Kurikulum $kurikulum): array
    {
        $pemetaan = $this->pemetaanCplMk($kurikulum);

        if ($pemetaan === []) {
            return ['angkatan_list' => [], 'pemetaan' => []];
        }

        $mkIds = collect($pemetaan)->pluck('mk_rows')->flatten(1)->pluck('mk_id')->unique()->values();

        $hasilRows = HasilCplMk::query()
            ->whereNotNull('nilai_akhir')
            ->whereHas('mkUnit', fn ($query) => $query->whereIn('mk_id', $mkIds))
            ->with(['mkUnit', 'kelasMkMahasiswa.mahasiswa'])
            ->get()
            ->filter(fn (HasilCplMk $hasil): bool => filled($hasil->kelasMkMahasiswa?->mahasiswa?->angkatan));

        $angkatanList = $hasilRows
            ->pluck('kelasMkMahasiswa.mahasiswa.angkatan')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $perMkAngkatan = $hasilRows->groupBy(
            fn (HasilCplMk $hasil): string => $hasil->cpl_id.'|'.$hasil->mkUnit->mk_id.'|'.$hasil->kelasMkMahasiswa->mahasiswa->angkatan,
        );
        $perMkKeseluruhan = $hasilRows->groupBy(
            fn (HasilCplMk $hasil): string => $hasil->cpl_id.'|'.$hasil->mkUnit->mk_id,
        );

        $ketercapaianPerCpl = $this->ketercapaianCplAllTime($kurikulum, $hasilRows);

        $pemetaanLengkap = collect($pemetaan)
            ->map(function (array $cplGroup) use ($angkatanList, $perMkAngkatan, $perMkKeseluruhan, $ketercapaianPerCpl): array {
                $mkRows = collect($cplGroup['mk_rows'])
                    ->map(function (array $mkRow) use ($cplGroup, $angkatanList, $perMkAngkatan, $perMkKeseluruhan): array {
                        $perAngkatan = [];

                        foreach ($angkatanList as $angkatan) {
                            $rows = $perMkAngkatan->get($cplGroup['cpl_id'].'|'.$mkRow['mk_id'].'|'.$angkatan);

                            $perAngkatan[$angkatan] = [
                                'rata_rata' => $rows !== null ? round((float) $rows->avg('nilai_akhir'), 2) : null,
                                'n' => $rows?->count() ?? 0,
                            ];
                        }

                        $rowsKeseluruhan = $perMkKeseluruhan->get($cplGroup['cpl_id'].'|'.$mkRow['mk_id']);

                        return [
                            ...$mkRow,
                            'per_angkatan' => $perAngkatan,
                            'rata_rata_keseluruhan' => $rowsKeseluruhan !== null
                                ? round((float) $rowsKeseluruhan->avg('nilai_akhir'), 2)
                                : null,
                        ];
                    })
                    ->all();

                return [
                    ...$cplGroup,
                    'mk_rows' => $mkRows,
                    'ketercapaian' => $ketercapaianPerCpl[$cplGroup['cpl_id']] ?? null,
                ];
            })
            ->all();

        return ['angkatan_list' => $angkatanList, 'pemetaan' => $pemetaanLengkap];
    }

    /**
     * Ketercapaian CPL ALL-TIME (semua semester histori hasil_cpl_mk untuk
     * MK-MK prodi ini) — mirip CplUnitAggregator::agregatDariMkUnits() tapi
     * tanpa filter semester_id tunggal.
     *
     * @param  Collection<int, HasilCplMk>  $hasilRows  hasil_cpl_mk yang sudah difilter utk MK-MK prodi ini
     * @return array<string, array{rata_rata: float|null, jumlah_mahasiswa: int, persentase_tercapai: float|null, tercapai: bool}>
     */
    protected function ketercapaianCplAllTime(Kurikulum $kurikulum, Collection $hasilRows): array
    {
        $target = (int) ($kurikulum->target_capaian_lulusan ?? 75);

        return $hasilRows
            ->groupBy('cpl_id')
            ->map(function (Collection $rows) use ($target): array {
                $perMahasiswa = $rows->groupBy(fn (HasilCplMk $hasil): string => (string) $hasil->kelasMkMahasiswa?->mahasiswa_id);

                $rataRataPerMahasiswa = $perMahasiswa->map(fn (Collection $baris): float => (float) $baris->avg('nilai_akhir'));

                $jumlahMahasiswa = $rataRataPerMahasiswa->count();
                $tercapaiCount = $rataRataPerMahasiswa->filter(fn (float $nilai): bool => $nilai >= $target)->count();
                $rataRataKeseluruhan = $rataRataPerMahasiswa->isNotEmpty() ? round((float) $rataRataPerMahasiswa->avg(), 2) : null;

                return [
                    'rata_rata' => $rataRataKeseluruhan,
                    'jumlah_mahasiswa' => $jumlahMahasiswa,
                    'persentase_tercapai' => $jumlahMahasiswa > 0 ? round($tercapaiCount / $jumlahMahasiswa * 100, 2) : null,
                    'tercapai' => $rataRataKeseluruhan !== null && $rataRataKeseluruhan >= $target,
                ];
            })
            ->all();
    }

    /**
     * Kode MkUnit (mis. "KU21511001") untuk tiap MK pada prodi kurikulum ini —
     * dipakai murni sebagai label kode; MkUnit yang aktif diprioritaskan,
     * fallback ke penawaran manapun bila tidak ada yang aktif.
     *
     * @param  Collection<int, string>  $mkIds
     * @return array<string, string>
     */
    protected function kodeMkUnitByMkId(Kurikulum $kurikulum, Collection $mkIds): array
    {
        return MkUnit::query()
            ->whereIn('mk_id', $mkIds)
            ->where('kurikulum_id', $kurikulum->id)
            ->get()
            ->groupBy('mk_id')
            ->map(fn (Collection $units): string => ($units->firstWhere('is_active', true) ?? $units->first())->kode)
            ->all();
    }
}
