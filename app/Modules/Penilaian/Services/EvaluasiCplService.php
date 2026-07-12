<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\CPL\Models\CplMk;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCpmk;
use App\Modules\Kalkulasi\Models\HasilSubcpmk;
use App\Modules\Kalkulasi\Services\CplMkCalculator;
use App\Modules\Kalkulasi\Services\CpmkCalculator;
use App\Modules\Kalkulasi\Services\SubcpmkCalculator;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Illuminate\Support\Collection;

/**
 * Ketercapaian CPL & distribusi nilai per kelas — dipakai tab Laporan >
 * Evaluasi CPL v1 (ringkasan per CPL) dan Evaluasi CPL v2 (rincian
 * CPL→CPMK→Sub-CPMK) pada halaman Input Nilai.
 *
 * Kalkulasi CPL/CPMK/Sub-CPMK (tabel hasil_subcpmk/hasil_cpmk/hasil_cpl_mk)
 * normalnya di-refresh lewat RecalkulasiCplJob yang di-queue, tapi worker
 * queue-nya tidak berjalan di environment ini — jadi kalkulasinya dijalankan
 * sinkron di sini (urutan sama seperti RecalkulasiCplJob::handle(), hanya
 * dua langkah agregasi unit-wide di ujungnya yang dilewati karena tidak
 * relevan untuk laporan per kelas).
 */
class EvaluasiCplService
{
    /**
     * Mengelompokkan kategori Evaluasi mentah (ujian, tugas, proyek, dst.)
     * ke label yang ditampilkan sebagai "Sumber Data" — mencerminkan
     * RencanaEvaluasiService::GRUP_KATEGORI (duplikasi kecil yang disengaja
     * karena konstanta itu private di sana).
     *
     * @var array<string, list<string>>
     */
    private const GRUP_KATEGORI = [
        'Pengetahuan/Kognitif' => ['ujian', 'tugas'],
        'Hasil Proyek/Studi Kasus' => ['proyek', 'studi_kasus'],
        'Aktivitas Partisipatif' => ['partisipasi'],
    ];

    public function jalankanKalkulasiSinkron(KelasMk $kelasMk): void
    {
        app(SubcpmkCalculator::class)->calculate($kelasMk->id);
        app(CpmkCalculator::class)->calculate($kelasMk->id);
        app(CplMkCalculator::class)->calculate($kelasMk->id, $kelasMk->semester_id);
    }

    public function targetCapaianLulusan(KelasMk $kelasMk): int
    {
        $academicUnitId = $kelasMk->mkUnit?->academic_unit_id;

        if ($academicUnitId === null) {
            return 75;
        }

        return (int) (Kurikulum::query()
            ->where('academic_unit_id', $academicUnitId)
            ->where('is_active', true)
            ->value('target_capaian_lulusan') ?? 75);
    }

    /**
     * @return Collection<int, string>
     */
    private function resolveKmmIds(KelasMk $kelasMk, ?string $kelasMkMahasiswaId): Collection
    {
        if ($kelasMkMahasiswaId !== null) {
            return collect([$kelasMkMahasiswaId]);
        }

        return KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasMk->id)
            ->pluck('id');
    }

    /**
     * Ketercapaian CPL rata-rata seluruh kelas, atau satu mahasiswa saja bila
     * `$kelasMkMahasiswaId` diisi (dipakai modal "Capaian" per mahasiswa).
     *
     * @return list<array{cpl_kode: string, cpl_deskripsi: string, kontribusi: list<array{nama: string, bobot: float}>, rata_rata: float|null, tercapai: bool}>
     */
    public function ketercapaianCplPerKelas(KelasMk $kelasMk, ?string $kelasMkMahasiswaId = null): array
    {
        $mkId = $kelasMk->mkUnit?->mk_id;

        if ($mkId === null) {
            return [];
        }

        $target = $this->targetCapaianLulusan($kelasMk);

        $kmmIds = $this->resolveKmmIds($kelasMk, $kelasMkMahasiswaId);

        return CplMk::query()
            ->where('mk_id', $mkId)
            ->with(['cplBok.cpl', 'mkCpmks'])
            ->get()
            ->filter(fn (CplMk $cplMk): bool => $cplMk->cplBok?->cpl !== null)
            ->map(function (CplMk $cplMk) use ($kelasMk, $kmmIds, $target): array {
                $cpl = $cplMk->cplBok->cpl;

                $mkCpmkIds = $cplMk->mkCpmks->pluck('id');

                $kontribusi = Subcpmk::query()
                    ->whereIn('mk_cpmk_id', $mkCpmkIds)
                    ->where('semester_id', $kelasMk->semester_id)
                    ->get()
                    ->flatMap(fn (Subcpmk $subcpmk) => SubcpmkAsesmenPemetaanService::rincianBobotEvaluasi($subcpmk->id)
                        ->map(fn (float $bobot, string $nama): array => ['nama' => $nama, 'bobot' => $bobot])
                        ->values())
                    ->values()
                    ->all();

                $rataRata = HasilCplMk::query()
                    ->where('cpl_id', $cpl->id)
                    ->where('mk_unit_id', $kelasMk->mk_unit_id)
                    ->where('semester_id', $kelasMk->semester_id)
                    ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
                    ->whereNotNull('nilai_akhir')
                    ->avg('nilai_akhir');

                $rataRata = $rataRata !== null ? round((float) $rataRata, 2) : null;

                return [
                    'cpl_kode' => $cpl->kode,
                    'cpl_deskripsi' => $cpl->deskripsi,
                    'kontribusi' => $kontribusi,
                    'rata_rata' => $rataRata,
                    'tercapai' => $rataRata !== null && $rataRata >= $target,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{huruf: string, jumlah: int, persentase: float}>
     */
    public function distribusiNilaiHuruf(KelasMk $kelasMk): array
    {
        $hurufUrutan = ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D', 'E'];

        $jumlahPerHuruf = KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasMk->id)
            ->whereNotNull('nilai_huruf')
            ->selectRaw('nilai_huruf, count(*) as jumlah')
            ->groupBy('nilai_huruf')
            ->pluck('jumlah', 'nilai_huruf');

        $total = (int) $jumlahPerHuruf->sum();

        return collect($hurufUrutan)
            ->map(function (string $huruf) use ($jumlahPerHuruf, $total): array {
                $jumlah = (int) ($jumlahPerHuruf->get($huruf) ?? 0);

                return [
                    'huruf' => $huruf,
                    'jumlah' => $jumlah,
                    'persentase' => $total > 0 ? round($jumlah / $total * 100, 2) : 0.0,
                ];
            })
            ->all();
    }

    /**
     * Rincian CPL → CPMK → Sub-CPMK → asesmen penyumbang, satu baris per
     * asesmen (Perkiraan Bobot/PK × Rerata Nilai/RN), dengan info rowspan
     * per level supaya tabel bertingkat bisa dirender lurus di Blade tanpa
     * perlu menghitung ulang $loop di tiga level nested sekaligus. Rata-rata
     * kelas, atau satu mahasiswa saja bila `$kelasMkMahasiswaId` diisi
     * (dipakai modal "Capaian" per mahasiswa — RN-nya jadi nilai mahasiswa
     * itu sendiri, bukan rata-rata kelas).
     *
     * @return list<array{
     *     cpl_kode: string, cpl_deskripsi: string, cpl_rowspan: int, cpl_awal: bool,
     *     cpl_rata_rata: float|null, cpl_target: int, cpl_tercapai: bool,
     *     cpmk_kode: string, cpmk_deskripsi: string, cpmk_rowspan: int, cpmk_awal: bool, cpmk_rata_rata: float|null,
     *     subcpmk_kode: string, subcpmk_deskripsi: string, subcpmk_rowspan: int, subcpmk_awal: bool, subcpmk_rata_rata: float|null,
     *     indikator: string, sumber_data: string, pk: float|null, rn: float|null, pk_x_rn: float|null,
     * }>
     */
    public function detailCplCpmkSubcpmk(KelasMk $kelasMk, ?string $kelasMkMahasiswaId = null): array
    {
        $mkId = $kelasMk->mkUnit?->mk_id;

        if ($mkId === null) {
            return [];
        }

        $target = $this->targetCapaianLulusan($kelasMk);
        $ketercapaianPerCpl = collect($this->ketercapaianCplPerKelas($kelasMk, $kelasMkMahasiswaId))->keyBy('cpl_kode');

        $kmmIds = $this->resolveKmmIds($kelasMk, $kelasMkMahasiswaId);

        $cplMks = CplMk::query()
            ->where('mk_id', $mkId)
            ->with(['cplBok.cpl', 'mkCpmks.cpmk'])
            ->get()
            ->filter(fn (CplMk $cplMk): bool => $cplMk->cplBok?->cpl !== null);

        $pohon = [];

        foreach ($cplMks as $cplMk) {
            $cpl = $cplMk->cplBok->cpl;
            $ketercapaian = $ketercapaianPerCpl->get($cpl->kode);

            $cpmkNodes = $this->susunCpmkNodes($cplMk->mkCpmks, $kelasMk, $kmmIds);

            $pohon[] = [
                'kode' => $cpl->kode,
                'deskripsi' => $cpl->deskripsi,
                'rata_rata' => $ketercapaian['rata_rata'] ?? null,
                'target' => $target,
                'tercapai' => $ketercapaian['tercapai'] ?? false,
                'cpmks' => $cpmkNodes,
            ];
        }

        return $this->flattenPohonCpl($pohon);
    }

    /**
     * @param  Collection<int, MkCpmk>  $mkCpmks
     * @param  Collection<int, string>  $kmmIds
     * @return list<array{kode: string, deskripsi: string, rata_rata: float|null, subcpmks: list<array{kode: string, deskripsi: string, rata_rata: float|null, baris: list<array{indikator: string, sumber_data: string, pk: float|null, rn: float|null, pk_x_rn: float|null}>}>}>
     */
    private function susunCpmkNodes(Collection $mkCpmks, KelasMk $kelasMk, Collection $kmmIds): array
    {
        $nodes = [];

        foreach ($mkCpmks as $mkCpmk) {
            if ($mkCpmk->cpmk === null) {
                continue;
            }

            $cpmk = $mkCpmk->cpmk;

            $cpmkRataRata = HasilCpmk::query()
                ->where('cpmk_id', $cpmk->id)
                ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
                ->whereNotNull('nilai_akhir')
                ->avg('nilai_akhir');

            $subcpmks = Subcpmk::query()
                ->where('mk_cpmk_id', $mkCpmk->id)
                ->where('semester_id', $kelasMk->semester_id)
                ->get();

            $nodes[] = [
                'kode' => $cpmk->kode,
                'deskripsi' => $cpmk->deskripsi,
                'rata_rata' => $cpmkRataRata !== null ? round((float) $cpmkRataRata, 2) : null,
                'subcpmks' => $this->susunSubcpmkNodes($subcpmks, $kmmIds),
            ];
        }

        if ($nodes === []) {
            $nodes[] = [
                'kode' => '—',
                'deskripsi' => '—',
                'rata_rata' => null,
                'subcpmks' => $this->susunSubcpmkNodes(collect(), $kmmIds),
            ];
        }

        return $nodes;
    }

    /**
     * @param  Collection<int, Subcpmk>  $subcpmks
     * @param  Collection<int, string>  $kmmIds
     * @return list<array{kode: string, deskripsi: string, rata_rata: float|null, baris: list<array{indikator: string, sumber_data: string, pk: float|null, rn: float|null, pk_x_rn: float|null}>}>
     */
    private function susunSubcpmkNodes(Collection $subcpmks, Collection $kmmIds): array
    {
        $nodes = [];

        foreach ($subcpmks as $subcpmk) {
            $subcpmkRataRata = HasilSubcpmk::query()
                ->where('subcpmk_id', $subcpmk->id)
                ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
                ->whereNotNull('nilai_akhir')
                ->avg('nilai_akhir');

            $pivots = SubcpmkKomponenPenilaian::query()
                ->where('subcpmk_id', $subcpmk->id)
                ->with('komponenPenilaian.evaluasi')
                ->get();

            $baris = [];

            foreach ($pivots as $pivot) {
                $rn = NilaiMahasiswa::query()
                    ->where('subcpmk_komponenpenilaian_id', $pivot->id)
                    ->whereIn('kelas_mk_mahasiswa_id', $kmmIds)
                    ->whereNotNull('nilai')
                    ->avg('nilai');
                $rn = $rn !== null ? round((float) $rn, 2) : null;

                $pk = round((float) ($pivot->komponenPenilaian?->bobot ?? 0) * (float) $pivot->bobot / 100, 2);
                $kodeAsesmen = $pivot->komponenPenilaian?->kode ?? '—';
                $labelKategori = $this->labelKategori($pivot->komponenPenilaian?->evaluasi?->kategori);

                $baris[] = [
                    'indikator' => $subcpmk->indikator ?: '—',
                    'sumber_data' => trim($kodeAsesmen.' - '.$labelKategori, ' -'),
                    'pk' => $pk,
                    'rn' => $rn,
                    'pk_x_rn' => $rn !== null ? round($pk * $rn / 100, 2) : null,
                ];
            }

            if ($baris === []) {
                $baris[] = [
                    'indikator' => $subcpmk->indikator ?: '—',
                    'sumber_data' => '—',
                    'pk' => null,
                    'rn' => null,
                    'pk_x_rn' => null,
                ];
            }

            $nodes[] = [
                'kode' => $subcpmk->kode,
                'deskripsi' => $subcpmk->deskripsi,
                'rata_rata' => $subcpmkRataRata !== null ? round((float) $subcpmkRataRata, 2) : null,
                'baris' => $baris,
            ];
        }

        if ($nodes === []) {
            $nodes[] = [
                'kode' => '—',
                'deskripsi' => '—',
                'rata_rata' => null,
                'baris' => [[
                    'indikator' => '—',
                    'sumber_data' => '—',
                    'pk' => null,
                    'rn' => null,
                    'pk_x_rn' => null,
                ]],
            ];
        }

        return $nodes;
    }

    /**
     * @param  list<array{kode: string, deskripsi: string, rata_rata: float|null, target: int, tercapai: bool, cpmks: list<array{kode: string, deskripsi: string, rata_rata: float|null, subcpmks: list<array{kode: string, deskripsi: string, rata_rata: float|null, baris: list<array{indikator: string, sumber_data: string, pk: float|null, rn: float|null, pk_x_rn: float|null}>}>}>}>  $pohon
     * @return list<array{
     *     cpl_kode: string, cpl_deskripsi: string, cpl_rowspan: int, cpl_awal: bool,
     *     cpl_rata_rata: float|null, cpl_target: int, cpl_tercapai: bool,
     *     cpmk_kode: string, cpmk_deskripsi: string, cpmk_rowspan: int, cpmk_awal: bool, cpmk_rata_rata: float|null,
     *     subcpmk_kode: string, subcpmk_deskripsi: string, subcpmk_rowspan: int, subcpmk_awal: bool, subcpmk_rata_rata: float|null,
     *     indikator: string, sumber_data: string, pk: float|null, rn: float|null, pk_x_rn: float|null,
     * }>
     */
    private function flattenPohonCpl(array $pohon): array
    {
        $hasil = [];

        foreach ($pohon as $cpl) {
            $totalBarisCpl = collect($cpl['cpmks'])
                ->sum(fn (array $cpmk): int => collect($cpmk['subcpmks'])->sum(fn (array $s): int => count($s['baris'])));
            $cplAwal = true;

            foreach ($cpl['cpmks'] as $cpmk) {
                $totalBarisCpmk = collect($cpmk['subcpmks'])->sum(fn (array $s): int => count($s['baris']));
                $cpmkAwal = true;

                foreach ($cpmk['subcpmks'] as $subcpmk) {
                    $totalBarisSubcpmk = count($subcpmk['baris']);
                    $subcpmkAwal = true;

                    foreach ($subcpmk['baris'] as $baris) {
                        $hasil[] = array_merge($baris, [
                            'cpl_kode' => $cpl['kode'],
                            'cpl_deskripsi' => $cpl['deskripsi'],
                            'cpl_rowspan' => $cplAwal ? $totalBarisCpl : 0,
                            'cpl_awal' => $cplAwal,
                            'cpl_rata_rata' => $cpl['rata_rata'],
                            'cpl_target' => $cpl['target'],
                            'cpl_tercapai' => $cpl['tercapai'],
                            'cpmk_kode' => $cpmk['kode'],
                            'cpmk_deskripsi' => $cpmk['deskripsi'],
                            'cpmk_rowspan' => $cpmkAwal ? $totalBarisCpmk : 0,
                            'cpmk_awal' => $cpmkAwal,
                            'cpmk_rata_rata' => $cpmk['rata_rata'],
                            'subcpmk_kode' => $subcpmk['kode'],
                            'subcpmk_deskripsi' => $subcpmk['deskripsi'],
                            'subcpmk_rowspan' => $subcpmkAwal ? $totalBarisSubcpmk : 0,
                            'subcpmk_awal' => $subcpmkAwal,
                            'subcpmk_rata_rata' => $subcpmk['rata_rata'],
                        ]);

                        $cplAwal = false;
                        $cpmkAwal = false;
                        $subcpmkAwal = false;
                    }
                }
            }
        }

        return $hasil;
    }

    private function labelKategori(?string $kategori): string
    {
        if ($kategori === null) {
            return '—';
        }

        foreach (self::GRUP_KATEGORI as $label => $kategoris) {
            if (in_array($kategori, $kategoris, true)) {
                return $label;
            }
        }

        return $kategori;
    }

    /**
     * Ringkasan ketercapaian CPL & CPMK & Sub-CPMK kelas ini, dipakai untuk
     * menyusun data 3 dari 4 grafik jaring laba-laba pada tab Laporan
     * (radar per-Asesmen memakai $columns + $rataRataKelas milik halaman
     * langsung, jadi tidak perlu lewat sini).
     *
     * @return array{
     *     cpl: list<array{label: string, nilai: float}>,
     *     cpmk: list<array{label: string, nilai: float}>,
     *     subcpmk: list<array{label: string, nilai: float}>,
     * }
     */
    public function ringkasanRadarKelas(KelasMk $kelasMk): array
    {
        $ketercapaianCpl = $this->ketercapaianCplPerKelas($kelasMk);
        $detail = $this->detailCplCpmkSubcpmk($kelasMk);

        return [
            'cpl' => collect($ketercapaianCpl)
                ->map(fn (array $row): array => ['label' => $row['cpl_kode'], 'nilai' => $row['rata_rata'] ?? 0.0])
                ->values()
                ->all(),
            'cpmk' => collect($detail)
                ->unique('cpmk_kode')
                ->map(fn (array $row): array => ['label' => $row['cpmk_kode'], 'nilai' => $row['cpmk_rata_rata'] ?? 0.0])
                ->values()
                ->all(),
            'subcpmk' => collect($detail)
                ->unique('subcpmk_kode')
                ->map(fn (array $row): array => ['label' => $row['subcpmk_kode'], 'nilai' => $row['subcpmk_rata_rata'] ?? 0.0])
                ->values()
                ->all(),
        ];
    }
}
