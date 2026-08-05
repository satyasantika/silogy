<?php

namespace App\Modules\Penilaian\Filament\Pages\Concerns;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Penilaian\Services\EvaluasiCplService;
use App\Modules\Penilaian\Services\PenilaianMatrixService;
use App\Modules\Penilaian\Services\RencanaEvaluasiService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * Data & komputasi tab "Laporan" (Portofolio, Evaluasi CPL, Hasil Analisis
 * per Mahasiswa, Laporan Lengkap) untuk satu KelasMk — murni baca, dipakai
 * bersama oleh InputNilai (dosen pengampu, kelas sendiri) dan
 * LaporanKoordinator (koordinator MK, seluruh kelas yang ia koordinatori).
 */
trait HasLaporanKelasMk
{
    /**
     * @var list<array{id: string, nim: string, nama: string, nilai_angka: float|null, nilai_huruf: string|null}>
     */
    public array $rows = [];

    /**
     * Baris mahasiswa yang sama dengan $rows, tapi diurutkan berdasarkan NIM
     * — dipakai tab Laporan > Portofolio (baca-saja).
     *
     * @var list<array{id: string, nim: string, nama: string, nilai_angka: float|null, nilai_huruf: string|null}>
     */
    public array $portofolioRows = [];

    /**
     * @var list<array{id: string, label: string, asesmen: string, subcpmk: string, evaluasi_kode: string|null, cpl: string|null, bobot: float}>
     */
    public array $columns = [];

    /**
     * Kolom laporan Portofolio, dikelompokkan per jenis penilaian (Evaluasi)
     * bukan per KomponenPenilaian — lihat PenilaianMatrixService::kolomEvaluasiDariKomponens().
     *
     * @var list<array{id: string, label: string, bobot: float, cpl: string|null, komponen_ids: list<string>}>
     */
    public array $kolomEvaluasi = [];

    /**
     * @var array<string, array<string, string|null>>
     */
    public array $nilai = [];

    /**
     * @var list<array{cpl_kode: string, cpl_deskripsi: string, kontribusi: list<array{nama: string, bobot: float}>, rata_rata: float|null, tercapai: bool}>
     */
    public array $ketercapaianCpl = [];

    /**
     * @var list<array{huruf: string, jumlah: int, persentase: float}>
     */
    public array $distribusiNilaiHuruf = [];

    /**
     * @var list<array{
     *     cpl_kode: string, cpl_deskripsi: string, cpl_rowspan: int, cpl_awal: bool,
     *     cpl_rata_rata: float|null, cpl_target: int, cpl_tercapai: bool,
     *     cpmk_kode: string, cpmk_deskripsi: string, cpmk_rowspan: int, cpmk_awal: bool, cpmk_rata_rata: float|null,
     *     subcpmk_kode: string, subcpmk_deskripsi: string, subcpmk_rowspan: int, subcpmk_awal: bool, subcpmk_rata_rata: float|null,
     *     indikator: string, sumber_data: string, pk: float|null, rn: float|null, pk_x_rn: float|null,
     * }>
     */
    public array $detailCplCpmkSubcpmk = [];

    /**
     * @var array{groups: list<array{label: string, bobot_persen: float, rows: list<array{evaluasi_nama: string, asesmen: list<array{kode: string, nama: string, bobot: float}>, bobot_total: float, cpls: list<array{kode: string, deskripsi: string|null}>, cpmks: list<array{kode: string, deskripsi: string|null}>}>}>, total_bobot: float}|null
     */
    public ?array $rencanaEvaluasi = null;

    public int $targetCapaianLulusan = 75;

    public bool $penugasanBelumSelesai = false;

    /**
     * Reset seluruh state laporan — dipanggil sebelum memuat ulang (atau
     * saat belum ada kelas terpilih).
     */
    protected function resetDataLaporan(): void
    {
        $this->nilai = [];
        $this->rows = [];
        $this->portofolioRows = [];
        $this->columns = [];
        $this->kolomEvaluasi = [];
        $this->ketercapaianCpl = [];
        $this->distribusiNilaiHuruf = [];
        $this->detailCplCpmkSubcpmk = [];
        $this->rencanaEvaluasi = null;
        $this->penugasanBelumSelesai = false;
    }

    /**
     * Memuat seluruh data laporan untuk satu KelasMk — pemanggil bertanggung
     * jawab memvalidasi penugasanSelesai() dan otorisasi sebelum memanggil ini.
     */
    protected function muatDataLaporan(KelasMk $kelasMk): void
    {
        $matrix = app(PenilaianMatrixService::class);
        $komponens = $matrix->komponenUntukKelas($kelasMk);

        $this->columns = $matrix->kolomDariKomponens($komponens);
        $this->kolomEvaluasi = $matrix->kolomEvaluasiDariKomponens($komponens);

        $this->rows = $matrix->barisUntukKelas($kelasMk);
        $this->portofolioRows = $matrix->barisUntukKelas($kelasMk, 'mahasiswas.nim');
        $this->nilai = $matrix->nilaiUntukMatrix($this->rows, $matrix->pivotIdsByKomponen($komponens));

        $evaluasiCpl = app(EvaluasiCplService::class);
        $evaluasiCpl->jalankanKalkulasiSinkron($kelasMk);
        $this->targetCapaianLulusan = $evaluasiCpl->targetCapaianLulusan($kelasMk);
        $this->ketercapaianCpl = $evaluasiCpl->ketercapaianCplPerKelas($kelasMk);
        $this->distribusiNilaiHuruf = $evaluasiCpl->distribusiNilaiHuruf($kelasMk);
        $this->detailCplCpmkSubcpmk = $evaluasiCpl->detailCplCpmkSubcpmk($kelasMk);
        $this->rencanaEvaluasi = app(RencanaEvaluasiService::class)->build($kelasMk->mkUnit?->mk_id, $kelasMk->semester_id);
    }

    /**
     * Rata-rata kelas per kolom asesmen, dihitung dari nilai yang sudah dimuat.
     *
     * @return array<string, float|null>
     */
    public function getRataRataKelasProperty(): array
    {
        return app(PenilaianMatrixService::class)->rataRataKelas($this->columns, $this->rows, $this->nilai);
    }

    /**
     * Rata-rata ketercapaian seluruh CPL pada MK ini — dipakai baris
     * rekapitulasi penutup tabel Evaluasi CPL v2.
     */
    public function getRataRataKeseluruhanCplProperty(): ?float
    {
        return app(EvaluasiCplService::class)->rataRataKeseluruhan($this->ketercapaianCpl);
    }

    /**
     * Nilai laporan Portofolio per (mahasiswa, jenis penilaian) — akumulasi
     * dari seluruh KomponenPenilaian di bawah jenis penilaian tersebut.
     *
     * @return array<string, array<string, float>>
     */
    public function getNilaiEvaluasiProperty(): array
    {
        return app(PenilaianMatrixService::class)->nilaiEvaluasiUntukMatrix($this->columns, $this->kolomEvaluasi, $this->nilai);
    }

    /**
     * @return array<string, float|null>
     */
    public function getRataRataEvaluasiProperty(): array
    {
        return app(PenilaianMatrixService::class)->rataRataKelas($this->kolomEvaluasi, $this->rows, $this->getNilaiEvaluasiProperty());
    }

    /**
     * Rata-rata kelas untuk kolom "Nilai" (Nilai Akhir) pada laporan
     * Portofolio.
     */
    public function getRataRataNilaiAkhirProperty(): ?float
    {
        return app(PenilaianMatrixService::class)->rataRataNilaiAkhir($this->rows);
    }

    /**
     * Huruf yang mewakili rata-rata kelas pada kolom "Grade" laporan
     * Portofolio, diturunkan dari rata-rata nilai akhir kelas.
     */
    public function getHurufRataRataKelasProperty(): ?string
    {
        $rataRataNilaiAkhir = $this->getRataRataNilaiAkhirProperty();

        return $rataRataNilaiAkhir !== null
            ? app(PenilaianMatrixService::class)->hurufDariNilaiAkhir($rataRataNilaiAkhir)
            : null;
    }

    /**
     * Warna badge nilai huruf (mis. A/A-, B+/B, C, D/E) untuk ditampilkan
     * berdampingan dengan nilai akhir mahasiswa pada baris tabel.
     *
     * @return array{class: string}
     */
    public function warnaNilaiHuruf(?string $huruf): array
    {
        return app(PenilaianMatrixService::class)->warnaNilaiHuruf($huruf);
    }

    /**
     * Tone badge ketercapaian CPMK/Sub-CPMK vs target kelulusan CPL.
     *
     * @return array{class: string}
     */
    public function warnaKetercapaian(?float $nilai, float|int|null $target): array
    {
        if ($nilai === null) {
            return ['class' => 'silogy-tone-neutral'];
        }

        $batas = $target !== null ? (float) $target : 75.0;

        if ($nilai >= $batas) {
            return ['class' => 'silogy-tone-success'];
        }

        if ($nilai >= $batas * 0.75) {
            return ['class' => 'silogy-tone-warning'];
        }

        return ['class' => 'silogy-tone-danger'];
    }

    /**
     * Identitas MK/kelas untuk kop laporan gabungan. "dosen" selalu diambil
     * dari dosen pengampu kelas yang sedang dilihat (bukan user yang login),
     * karena koordinator MK bisa melihat laporan kelas milik dosen lain.
     *
     * @return array{nama: string, kode: string, sks: int, semester: string, dosen: string, target: int}
     */
    public function getIdentitasMkProperty(): array
    {
        $mk = $this->getMkTerpilihProperty();
        $kelasMk = $this->kelasMkId !== null
            ? KelasMk::query()->with(['mkUnit', 'dosenPengampu'])->find($this->kelasMkId)
            : null;

        return [
            'nama' => $mk?->nama ?? '—',
            'kode' => $kelasMk?->mkUnit?->kode ?? '—',
            'sks' => (int) ($mk?->sks ?? 0),
            'semester' => $this->getSemesterTerpilihProperty(),
            'dosen' => $kelasMk?->dosenPengampu?->namaDenganGelar() ?? '—',
            'target' => $this->targetCapaianLulusan,
        ];
    }

    /**
     * Data 4 grafik jaring laba-laba tab Laporan (CPL, CPMK, Sub-CPMK, dan
     * rata-rata per Asesmen), sudah dalam bentuk siap-pakai Chart.js
     * (`labels` + satu `datasets[0].data`).
     *
     * @return array<string, array{labels: list<string>, data: list<float>}>
     */
    public function getRadarDataProperty(): array
    {
        if ($this->kelasMkId === null) {
            return [];
        }

        $kelasMk = KelasMk::query()->with('mkUnit')->find($this->kelasMkId);

        if (! $kelasMk instanceof KelasMk) {
            return [];
        }

        $ringkasan = app(EvaluasiCplService::class)->ringkasanRadarKelas($kelasMk);

        $asesmen = collect($this->columns)
            ->map(fn (array $column): array => [
                'label' => $column['asesmen'],
                'nilai' => $this->rataRataKelas[$column['id']] ?? 0.0,
            ])
            ->values()
            ->all();

        $keSet = fn (array $items): array => [
            'labels' => collect($items)->pluck('label')->all(),
            'data' => collect($items)->pluck('nilai')->map(fn ($n): float => (float) $n)->all(),
        ];

        return [
            'cpl' => $keSet($ringkasan['cpl']),
            'cpmk' => $keSet($ringkasan['cpmk']),
            'subcpmk' => $keSet($ringkasan['subcpmk']),
            'asesmen' => $keSet($asesmen),
        ];
    }

    /**
     * Tombol "Capaian" pada tab Laporan > Hasil Analisis per Mahasiswa —
     * satu instance per baris mahasiswa, di-bind ke kmmId lewat ->arguments()
     * saat dirender (lihat partial laporan-kelas-tabs.blade.php).
     */
    public function capaianMahasiswaAction(): Action
    {
        return Action::make('capaianMahasiswa')
            ->label('Capaian')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->size('sm')
            ->modalWidth(Width::SixExtraLarge)
            ->modalHeading(function (array $arguments): string {
                $mahasiswa = collect($this->rows)->firstWhere('id', $arguments['kmmId'] ?? null);

                if ($mahasiswa === null) {
                    return 'Detail Capaian Mahasiswa';
                }

                return sprintf(
                    'Detail Capaian Mahasiswa — %s - %s',
                    $mahasiswa['nim'],
                    mb_strtoupper($mahasiswa['nama']),
                );
            })
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Tutup')
            ->schema(fn (array $arguments): array => [
                Placeholder::make('detail_capaian_mahasiswa')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => $this->capaianMahasiswaHtml($arguments['kmmId'] ?? null)),
            ]);
    }

    protected function capaianMahasiswaHtml(?string $kmmId): HtmlString
    {
        if ($kmmId === null || $this->kelasMkId === null) {
            return new HtmlString('<p style="font-size:13px;opacity:.7;">Data tidak ditemukan.</p>');
        }

        $kelasMk = KelasMk::query()->with('mkUnit')->find($this->kelasMkId);

        if (! $kelasMk instanceof KelasMk) {
            return new HtmlString('<p style="font-size:13px;opacity:.7;">Data tidak ditemukan.</p>');
        }

        $mahasiswa = collect($this->rows)->firstWhere('id', $kmmId);
        $evaluasiCpl = app(EvaluasiCplService::class);

        $ringkasanRadar = $evaluasiCpl->ringkasanRadarKelas($kelasMk, $kmmId);
        $keSet = fn (array $items): array => [
            'labels' => collect($items)->pluck('label')->all(),
            'data' => collect($items)->pluck('nilai')->map(fn ($n): float => (float) $n)->all(),
        ];

        $nilaiEvaluasiMahasiswa = $this->getNilaiEvaluasiProperty()[$kmmId] ?? [];
        $radarPenugasan = [
            'labels' => collect($this->kolomEvaluasi)->pluck('label')->all(),
            'data' => collect($this->kolomEvaluasi)
                ->map(fn (array $kolom): float => $nilaiEvaluasiMahasiswa[$kolom['id']] ?? 0.0)
                ->all(),
        ];

        return new HtmlString(view('filament.modules.penilaian.partials.capaian-mahasiswa', [
            'mahasiswa' => $mahasiswa,
            'warnaHuruf' => $this->warnaNilaiHuruf($mahasiswa['nilai_huruf'] ?? null),
            'ketercapaian' => $evaluasiCpl->ketercapaianCplPerKelas($kelasMk, $kmmId),
            'detail' => $evaluasiCpl->detailCplCpmkSubcpmk($kelasMk, $kmmId),
            'radarCpmk' => $keSet($ringkasanRadar['cpmk']),
            'radarSubcpmk' => $keSet($ringkasanRadar['subcpmk']),
            'radarPenugasan' => $radarPenugasan,
            'penilaianTersedia' => ($mahasiswa['nilai_angka'] ?? null) !== null,
            'kmmId' => $kmmId,
        ])->render());
    }

    abstract public function getMkTerpilihProperty(): mixed;

    abstract public function getSemesterTerpilihProperty(): string;
}
