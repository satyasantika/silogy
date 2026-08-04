<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\CPL\Models\CplMk;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Services\EvaluasiCplService;
use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Collection;

/**
 * Data untuk halaman "Analisis MK Prodi" — berbeda dari seluruh layanan
 * Penilaian lain di app ini (yang selalu per satu KelasMk), service ini
 * melihat SATU KURIKULUM sekaligus: semua CPL yang dibebankan kurikulum itu
 * dan semua MK yang membebaninya.
 *
 * Kurikulum fakultas/universitas tidak pernah punya mk_units/KelasMk sendiri
 * (lihat mkUnitIdsUntukKurikulum()) — penawaran & nilai mahasiswa selalu
 * tercatat di mk_units milik prodi turunan yang MENGADAPTASI MK kurikulum
 * itu. Jadi untuk kurikulum non-prodi, mk_unit_ids adalah ROLLUP lintas
 * semua prodi turunan yang mengadaptasi, bukan milik kurikulum itu sendiri.
 *
 * Adaptasi MK lintas unit TIDAK hanya terjadi di atas prodi: mk_units milik
 * prodi sendiri pun boleh menunjuk ke Mk yang sebenarnya dimiliki unit
 * induknya (fakultas/universitas) — lihat
 * MkUnitResource::adaptableMkOptions(). Karena itu cakupan CPL pada
 * pemetaanCplMk() HARUS mengikuti keanggotaan MK (mk_unit_ids -> mk_id),
 * bukan kepemilikan CPL — MK adaptasi membebani CPL milik unit induknya,
 * bukan CPL milik kurikulum yang sedang dikerjakan.
 */
class AnalisisMkProdiService
{
    /**
     * Kumpulan mk_units yang relevan untuk kurikulum ini:
     * - Prodi: mk_units miliknya sendiri (kurikulum_id = $kurikulum->id).
     * - Fakultas/universitas: mk_units milik SEMUA prodi turunan yang
     *   mk_id-nya diadaptasi dari Mk milik kurikulum ini (is_active saja,
     *   supaya rollup hanya menghitung penawaran yang sedang berjalan).
     *
     * @return Collection<int, string>
     */
    public function mkUnitIdsUntukKurikulum(Kurikulum $kurikulum): Collection
    {
        if ($kurikulum->academicUnit?->isProdi()) {
            return MkUnit::query()->where('kurikulum_id', $kurikulum->id)->pluck('id');
        }

        $mkIdsSumber = Mk::query()->where('kurikulum_id', $kurikulum->id)->pluck('id');

        if ($mkIdsSumber->isEmpty()) {
            return collect();
        }

        return MkUnit::query()
            ->where('is_active', true)
            ->whereIn('mk_id', $mkIdsSumber)
            ->whereHas('academicUnit', fn ($query) => $query->where('type', 'study_program'))
            ->pluck('id');
    }

    /**
     * Tab 1 — "Pemetaan Rencana Asesmen CPL": murni data kurikulum
     * (CplMk.bobot), tidak bergantung pada nilai mahasiswa sama sekali.
     *
     * Dicakup lewat GABUNGAN dua kondisi: CPL milik kurikulum ini sendiri
     * (supaya CPL yang belum ditawarkan MK manapun tetap tampil, mis. di
     * halaman fakultas/universitas sebelum ada prodi turunan yang
     * mengadaptasi) ATAU CPL yang dibebani lewat keanggotaan MK pada
     * mk_unit_ids (penawaran MK kurikulum ini) — MK adaptasi (lihat
     * docblock kelas) membebani CPL milik unit induknya, dan itu tetap
     * harus tampil di sini walau CPL-nya bukan milik kurikulum ini.
     *
     * 'kontribusi' adalah PORSI RELATIF tiap MK terhadap satu CPL, hasil
     * normalisasi proporsional dari (bobot_mentah × SKS) sehingga totalnya
     * per CPL tepat 100% — bukan angka mentah cpl_mk.bobot (tersedia
     * terpisah sebagai 'bobot_mentah'). SKS dipakai karena beban akademik
     * MK yang lebih besar (lebih banyak jam/SKS) wajar menyumbang lebih
     * besar pada ketercapaian CPL. Normalisasi ini murni penyajian: cpl_mk
     * tidak disentuh, karena invarian yang ditegakkan pada
     * /interaksi/cpl-mk adalah PER BARIS MK (lihat
     * NormalisasiBobotCplMkService), sehingga jumlah mentah per CPL bisa
     * berapa saja.
     *
     * Catatan cakupan: pada kurikulum fakultas/universitas (rollup) dan MK
     * adaptasi, himpunan MK yang tercakup bisa parsial — 100% di sini
     * relatif terhadap MK yang masuk mk_unit_ids kurikulum yang sedang
     * dikerjakan, bukan terhadap seluruh MK yang membebani CPL itu.
     *
     * @param  ?Collection<int, string>  $mkUnitIds  default: mkUnitIdsUntukKurikulum($kurikulum)
     * @return list<array{
     *     cpl_id: string,
     *     cpl_kode: string,
     *     cpl_deskripsi: string,
     *     mk_rows: list<array{mk_id: string, nama: string, kode: string, sks: int, kontribusi: float, bobot_mentah: float}>,
     * }>
     */
    public function pemetaanCplMk(Kurikulum $kurikulum, ?Collection $mkUnitIds = null): array
    {
        $mkUnitIds ??= $this->mkUnitIdsUntukKurikulum($kurikulum);

        $mkIds = $mkUnitIds->isNotEmpty()
            ? MkUnit::query()->whereIn('id', $mkUnitIds)->pluck('mk_id')->unique()
            : collect();

        // Tidak pakai Kurikulum::cplMks() — whereColumn() di dalamnya
        // mengasumsikan tabel "kurikulum" sudah ter-join di query luar
        // (subquery-nya gagal bila diakses langsung dari instance model).
        $cplMks = CplMk::query()
            ->where(function ($query) use ($kurikulum, $mkIds): void {
                $query->whereHas('cplBok.cpl', fn ($q) => $q->where('kurikulum_id', $kurikulum->id))
                    ->when($mkIds->isNotEmpty(), fn ($q) => $q->orWhereIn('mk_id', $mkIds));
            })
            ->with(['cplBok.cpl', 'mk'])
            ->get()
            ->filter(fn (CplMk $pivot): bool => $pivot->cplBok?->cpl !== null && $pivot->mk !== null);

        if ($cplMks->isEmpty()) {
            return [];
        }

        $kodeMkUnitByMkId = $this->kodeMkUnitByMkId($mkUnitIds, $cplMks->pluck('mk_id')->unique());

        return $cplMks
            // Dikelompokkan per id CPL (bukan kode) — MK adaptasi bisa
            // membawa CPL dari kurikulum lain, jadi kode CPL tidak lagi
            // pasti unik lintas hasil sekumpulan ini.
            ->groupBy(fn (CplMk $pivot): string => $pivot->cplBok->cpl->id)
            ->map(function (Collection $pivots) use ($kodeMkUnitByMkId): array {
                $cpl = $pivots->first()->cplBok->cpl;

                $mkRowsMentah = $pivots
                    ->groupBy('mk_id')
                    ->map(function (Collection $samaMk) use ($kodeMkUnitByMkId): array {
                        $mk = $samaMk->first()->mk;

                        return [
                            'mk_id' => $mk->id,
                            'nama' => $mk->nama,
                            'kode' => $kodeMkUnitByMkId[$mk->id] ?? '—',
                            'sks' => $mk->total_sks,
                            'bobot_mentah' => round((float) $samaMk->sum('bobot'), 2),
                        ];
                    })
                    ->sortBy('nama')
                    ->values();

                $kontribusiPerMk = $this->kontribusiPerCpl($mkRowsMentah);

                $mkRows = $mkRowsMentah
                    ->map(fn (array $mkRow): array => [
                        ...$mkRow,
                        'kontribusi' => $kontribusiPerMk[$mkRow['mk_id']] ?? 0.0,
                    ])
                    ->all();

                return [
                    'cpl_id' => $cpl->id,
                    'cpl_kode' => $cpl->kode,
                    'cpl_deskripsi' => $cpl->deskripsi,
                    'mk_rows' => $mkRows,
                ];
            })
            ->sortBy('cpl_kode')
            ->values()
            ->all();
    }

    /**
     * Porsi relatif tiap MK terhadap satu CPL: (bobot mentah × SKS) dibagi
     * total (bobot mentah × SKS) seluruh MK penyumbang CPL itu, dibulatkan
     * ke 2 desimal dengan sisa pembulatan dikoreksi pada MK berbobot
     * terbesar sehingga totalnya tepat 100% (lihat
     * BobotNormalizer::keSeratus()).
     *
     * Bila total bobot×SKS CPL itu 0 (atau negatif) — misalnya semua SKS
     * 0 atau semua bobot 0 — tidak ada porsi yang bisa dihitung;
     * dikembalikan kosong supaya pemanggil memakai 0.0.
     *
     * @param  Collection<int, array{mk_id: string, nama: string, kode: string, sks: int, bobot_mentah: float}>  $mkRows
     * @return array<string, float>
     */
    protected function kontribusiPerCpl(Collection $mkRows): array
    {
        return BobotNormalizer::keSeratus(
            $mkRows->mapWithKeys(fn (array $mkRow): array => [
                $mkRow['mk_id'] => $mkRow['bobot_mentah'] * max(0, (int) $mkRow['sks']),
            ]),
        );
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
     *
     * @param  ?Collection<int, string>  $mkUnitIds  default: mkUnitIdsUntukKurikulum($kurikulum)
     */
    public function sinkronkanKalkulasiProdi(Kurikulum $kurikulum, ?Collection $mkUnitIds = null): void
    {
        $mkUnitIds ??= $this->mkUnitIdsUntukKurikulum($kurikulum);

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
     * pernah ditawarkan) dan ketercapaian CPL all-time yang dihitung
     * TERTIMBANG memakai 'kontribusi' dari tab 1 (lihat
     * ketercapaianCplAllTime()).
     *
     * @param  ?Collection<int, string>  $mkUnitIds  default: mkUnitIdsUntukKurikulum($kurikulum)
     * @return array{
     *     angkatan_list: list<string>,
     *     pemetaan: list<array{
     *         cpl_id: string,
     *         cpl_kode: string,
     *         cpl_deskripsi: string,
     *         ketercapaian: array{rata_rata: float|null, jumlah_mahasiswa: int, persentase_tercapai: float|null, tercapai: bool}|null,
     *         mk_rows: list<array{
     *             mk_id: string, nama: string, kode: string, sks: int, kontribusi: float, bobot_mentah: float,
     *             per_angkatan: array<string, array{rata_rata: float|null, n: int}>,
     *             rata_rata_keseluruhan: float|null,
     *         }>,
     *     }>,
     * }
     */
    public function hasilAnalisisPerAngkatan(Kurikulum $kurikulum, ?Collection $mkUnitIds = null): array
    {
        $mkUnitIds ??= $this->mkUnitIdsUntukKurikulum($kurikulum);

        $pemetaan = $this->pemetaanCplMk($kurikulum, $mkUnitIds);

        if ($pemetaan === []) {
            return ['angkatan_list' => [], 'pemetaan' => []];
        }

        // Difilter lewat mk_unit_id (bukan mk_id saja) supaya kelas milik
        // kurikulum/prodi LAIN yang kebetulan memakai mk_id sama (mis. MK
        // yang diadaptasi ke beberapa prodi turunan) tidak ikut terhitung
        // di luar rollup mk_unit_ids yang sudah diresolusi di atas.
        $hasilRows = HasilCplMk::query()
            ->whereNotNull('nilai_akhir')
            ->whereHas('mkUnit', fn ($query) => $query->whereIn('id', $mkUnitIds))
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

        // Peta bobot untuk ketercapaian tertimbang, diambil dari $pemetaan
        // yang sudah dihitung di atas — tidak ada query tambahan.
        $kontribusiPerCplMk = collect($pemetaan)
            ->mapWithKeys(fn (array $cplGroup): array => [
                $cplGroup['cpl_id'] => collect($cplGroup['mk_rows'])
                    ->mapWithKeys(fn (array $mkRow): array => [$mkRow['mk_id'] => $mkRow['kontribusi']])
                    ->all(),
            ])
            ->all();

        $ketercapaianPerCpl = $this->ketercapaianCplAllTime($kurikulum, $hasilRows, $kontribusiPerCplMk);

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
     * Nilai tiap mahasiswa dihitung TERTIMBANG memakai kontribusi MK ke CPL
     * (lihat nilaiCplTertimbang()), jadi MK dengan bobot besar lebih
     * menentukan ketercapaian. Konsekuensinya angka di halaman ini berbeda
     * basis dari dashboard CPL yang memakai hasil_cpl_mk.nilai_berbobot
     * (CplUnitAggregator::agregatDariMkUnits()) — penyelarasan keduanya di
     * luar cakupan perubahan ini.
     *
     * @param  Collection<int, HasilCplMk>  $hasilRows  hasil_cpl_mk yang sudah difilter utk MK-MK prodi ini
     * @param  array<string, array<string, float>>  $kontribusiPerCplMk  cpl_id => (mk_id => kontribusi ternormalisasi)
     * @return array<string, array{rata_rata: float|null, jumlah_mahasiswa: int, persentase_tercapai: float|null, tercapai: bool}>
     */
    protected function ketercapaianCplAllTime(Kurikulum $kurikulum, Collection $hasilRows, array $kontribusiPerCplMk = []): array
    {
        $target = (int) ($kurikulum->target_capaian_lulusan ?? 75);

        return $hasilRows
            ->groupBy('cpl_id')
            ->map(function (Collection $rows, string $cplId) use ($target, $kontribusiPerCplMk): array {
                $kontribusiPerMk = $kontribusiPerCplMk[$cplId] ?? [];

                $perMahasiswa = $rows->groupBy(fn (HasilCplMk $hasil): string => (string) $hasil->kelasMkMahasiswa?->mahasiswa_id);

                $rataRataPerMahasiswa = $perMahasiswa->map(
                    fn (Collection $baris): float => $this->nilaiCplTertimbang($baris, $kontribusiPerMk),
                );

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
     * Nilai satu mahasiswa untuk satu CPL: rata-rata TERTIMBANG dari rerata
     * nilainya di tiap MK, dengan bobot berupa kontribusi MK ke CPL itu.
     *
     * Bobot dinormalisasi ulang ke MK yang BENAR-BENAR sudah ditempuh
     * (pembagi = Σ bobot MK yang punya nilai, bukan 100) supaya mahasiswa
     * angkatan baru yang belum menempuh seluruh MK penyumbang CPL tidak
     * tertekan nilainya. Bila tidak ada MK berbobot yang ditempuh, jatuh
     * kembali ke rata-rata sederhana agar hasilnya tidak hilang.
     *
     * @param  Collection<int, HasilCplMk>  $baris  hasil_cpl_mk satu mahasiswa pada satu CPL
     * @param  array<string, float>  $kontribusiPerMk
     */
    protected function nilaiCplTertimbang(Collection $baris, array $kontribusiPerMk): float
    {
        $rerataPerMk = $baris
            ->groupBy(fn (HasilCplMk $hasil): string => (string) $hasil->mkUnit?->mk_id)
            ->map(fn (Collection $samaMk): float => (float) $samaMk->avg('nilai_akhir'));

        $jumlahBerbobot = 0.0;
        $totalBobot = 0.0;

        foreach ($rerataPerMk as $mkId => $rerata) {
            $bobot = (float) ($kontribusiPerMk[$mkId] ?? 0);

            if ($bobot <= 0) {
                continue;
            }

            $jumlahBerbobot += $rerata * $bobot;
            $totalBobot += $bobot;
        }

        if ($totalBobot <= 0) {
            return (float) $baris->avg('nilai_akhir');
        }

        return $jumlahBerbobot / $totalBobot;
    }

    /**
     * Kode MkUnit (mis. "KU21511001") untuk tiap MK pada mk_unit_ids ini —
     * dipakai murni sebagai label kode; MkUnit yang aktif diprioritaskan,
     * fallback ke penawaran manapun bila tidak ada yang aktif. Pada rollup
     * fakultas/universitas, satu mk_id bisa dimiliki lebih dari satu
     * mk_unit (prodi berbeda mengadaptasi dengan kode masing-masing) —
     * semua kode unik ditampilkan digabung koma.
     *
     * @param  Collection<int, string>  $mkUnitIds
     * @param  Collection<int, string>  $mkIds
     * @return array<string, string>
     */
    protected function kodeMkUnitByMkId(Collection $mkUnitIds, Collection $mkIds): array
    {
        return MkUnit::query()
            ->whereIn('mk_id', $mkIds)
            ->whereIn('id', $mkUnitIds)
            ->get()
            ->groupBy('mk_id')
            ->map(function (Collection $units): string {
                $aktif = $units->where('is_active', true);
                $dipakai = $aktif->isNotEmpty() ? $aktif : $units;

                return $dipakai->pluck('kode')->unique()->sort()->implode(', ');
            })
            ->all();
    }

    /**
     * Rincian "prodi mana pakai kode apa" untuk satu MK — dipakai modal
     * "Lihat kode per prodi" pada halaman Analisis MK fakultas/universitas
     * (lihat tabel-pemetaan-cpl-mk.blade.php). Dibatasi ke $mkUnitIds yang
     * sama dengan himpunan rollup kurikulum yang dikerjakan saat ini, supaya
     * hanya prodi yang benar-benar bagian dari kurikulum itu yang tampil.
     *
     * @param  Collection<int, string>  $mkUnitIds
     * @return Collection<int, array{prodi: string, kode: string}>
     */
    public function kodePerProdiUntukMk(string $mkId, Collection $mkUnitIds): Collection
    {
        return MkUnit::query()
            ->where('mk_id', $mkId)
            ->whereIn('id', $mkUnitIds)
            ->with('academicUnit')
            ->get()
            ->map(fn (MkUnit $unit): array => [
                'prodi' => $unit->academicUnit?->nama_lengkap ?? '—',
                'kode' => $unit->kode,
            ])
            ->sortBy('prodi')
            ->values();
    }
}
