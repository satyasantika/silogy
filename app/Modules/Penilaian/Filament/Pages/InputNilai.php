<?php

namespace App\Modules\Penilaian\Filament\Pages;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Penilaian\Filament\Pages\Concerns\HasKelasMkDosenPicker;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Policies\InputNilaiPolicy;
use App\Modules\Penilaian\Services\EvaluasiCplService;
use App\Modules\Penilaian\Services\InputNilaiMatrixClipboardService;
use App\Modules\Penilaian\Services\PenilaianMatrixService;
use App\Modules\Penilaian\Services\RencanaEvaluasiService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class InputNilai extends Page
{
    use HasKelasMkDosenPicker;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Input Nilai';

    protected static string|\UnitEnum|null $navigationGroup = 'Penilaian';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'penilaian/input-nilai';

    protected static ?string $title = 'Input Nilai';

    protected string $view = 'filament.modules.penilaian.pages.input-nilai';

    /**
     * @var array<string, array<string, string|null>>
     */
    public array $nilai = [];

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
     * Subset id kolom (KomponenPenilaian) yang dipilih untuk ditampilkan —
     * filter tampilan (ikut mempersempit Salin matriks), tidak memengaruhi
     * data yang disimpan lewat Simpan/Tempel dari Excel.
     *
     * @var list<string>
     */
    public array $kolomTerpilih = [];

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
     * @var array{groups: list<array{label: string, bobot_persen: float, rows: list<array{evaluasi_nama: string, asesmen: list<array{kode: string, nama: string, bobot: float}>, bobot_total: float, cpl_kodes: list<string>, cpmk_kodes: list<string>}>}>, total_bobot: float}|null
     */
    public ?array $rencanaEvaluasi = null;

    public int $targetCapaianLulusan = 75;

    public bool $showKalkulasiBadge = false;

    public bool $penugasanBelumSelesai = false;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && app(InputNilaiPolicy::class)->access($user);
    }

    protected function afterKelasBerubah(): void
    {
        $this->showKalkulasiBadge = false;
    }

    /**
     * Kolom yang sedang ditampilkan di layar setelah difilter lewat tombol
     * Filter kolom — hanya memengaruhi tampilan (dan Salin matriks), bukan
     * data yang tersimpan.
     *
     * @return list<array{id: string, label: string, asesmen: string, subcpmk: string, evaluasi_kode: string|null, cpl: string|null, bobot: float}>
     */
    public function getColumnsTampilProperty(): array
    {
        if ($this->kolomTerpilih === []) {
            return $this->columns;
        }

        return collect($this->columns)
            ->filter(fn (array $column): bool => in_array($column['id'], $this->kolomTerpilih, true))
            ->values()
            ->all();
    }

    /**
     * Rata-rata kelas per kolom asesmen, dihitung langsung dari nilai yang
     * sedang diisi di form (belum tentu tersimpan).
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
     * @return array{bg: string, fg: string}
     */
    public function warnaNilaiHuruf(?string $huruf): array
    {
        return app(PenilaianMatrixService::class)->warnaNilaiHuruf($huruf);
    }

    /**
     * Identitas MK/kelas untuk kop laporan gabungan.
     *
     * @return array{nama: string, kode: string, sks: int, semester: string, dosen: string, target: int}
     */
    public function getIdentitasMkProperty(): array
    {
        $mk = $this->getMkTerpilihProperty();
        $kelasMk = $this->kelasMkId !== null
            ? KelasMk::query()->with('mkUnit')->find($this->kelasMkId)
            : null;
        $user = auth()->user();

        return [
            'nama' => $mk?->nama ?? '—',
            'kode' => $kelasMk?->mkUnit?->kode ?? '—',
            'sks' => (int) ($mk?->sks ?? 0),
            'semester' => $this->getSemesterTerpilihProperty(),
            'dosen' => $user instanceof User ? ($user->full_name ?? '—') : '—',
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

    public function loadMatrix(): void
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

        if ($this->kelasMkId === null) {
            return;
        }

        $kelasMk = KelasMk::query()->with('mkUnit')->find($this->kelasMkId);

        if (! $kelasMk instanceof KelasMk) {
            return;
        }

        // Dosen menilai hanya setelah koordinator MK menyelesaikan penugasan.
        $this->penugasanBelumSelesai = ! $kelasMk->penugasanSelesai();

        if ($this->penugasanBelumSelesai) {
            return;
        }

        Gate::authorize('inputNilai', $kelasMk);

        $matrix = app(PenilaianMatrixService::class);
        $komponens = $matrix->komponenUntukKelas($kelasMk);

        $this->columns = $matrix->kolomDariKomponens($komponens);
        $this->kolomEvaluasi = $matrix->kolomEvaluasiDariKomponens($komponens);

        $idKolom = collect($this->columns)->pluck('id')->all();

        // Reset filter tampilan ke "semua kolom" hanya saat pertama kali
        // dimuat atau saat berpindah kelas/MK (kolom terpilih lama tidak
        // valid lagi) — filter yang masih relevan (mis. setelah Simpan atau
        // Tempel dari Excel memuat ulang matriks) tetap dipertahankan.
        if ($this->kolomTerpilih === [] || array_diff($this->kolomTerpilih, $idKolom) !== []) {
            $this->kolomTerpilih = $idKolom;
        }

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

    public function save(): void
    {
        if ($this->kelasMkId === null) {
            return;
        }

        $kelasMk = KelasMk::query()->with('mkUnit')->findOrFail($this->kelasMkId);

        Gate::authorize('inputNilai', $kelasMk);

        $allowedKmmIds = KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasMk->id)
            ->pluck('id')
            ->all();

        $matrix = app(PenilaianMatrixService::class);
        $pivotIdsByKomponen = $matrix->pivotIdsByKomponen($matrix->komponenUntukKelas($kelasMk));

        foreach ($this->nilai as $kmmId => $cells) {
            if (! in_array($kmmId, $allowedKmmIds, true)) {
                throw ValidationException::withMessages([
                    'nilai' => 'Data mahasiswa tidak valid untuk kelas ini.',
                ]);
            }

            foreach ($cells as $komponenId => $value) {
                if (! array_key_exists($komponenId, $pivotIdsByKomponen)) {
                    throw ValidationException::withMessages([
                        'nilai' => 'Data komponen penilaian tidak valid untuk kelas ini.',
                    ]);
                }

                $pivotIds = $pivotIdsByKomponen[$komponenId];

                if ($value === null || $value === '') {
                    // Mengosongkan sel = mereset nilai asesmen ini untuk mahasiswa
                    // yang bersangkutan (dipakai juga saat tempel dari Excel hanya
                    // menyertakan sebagian asesmen) — hanya baris yang memang sudah
                    // tersimpan yang disentuh, agar tidak membuat baris kosong baru.
                    NilaiMahasiswa::query()
                        ->whereIn('subcpmk_komponenpenilaian_id', $pivotIds)
                        ->where('kelas_mk_mahasiswa_id', $kmmId)
                        ->whereNotNull('nilai')
                        ->get()
                        ->each(fn (NilaiMahasiswa $n) => $n->update(['nilai' => null]));

                    continue;
                }

                $numeric = (float) $value;

                if ($numeric < 0 || $numeric > 100) {
                    throw ValidationException::withMessages([
                        "nilai.{$kmmId}.{$komponenId}" => 'Nilai harus antara 0 dan 100.',
                    ]);
                }

                foreach ($pivotIds as $pivotId) {
                    NilaiMahasiswa::query()->updateOrCreate(
                        [
                            'subcpmk_komponenpenilaian_id' => $pivotId,
                            'kelas_mk_mahasiswa_id' => $kmmId,
                        ],
                        [
                            'nilai' => $numeric,
                        ],
                    );
                }
            }
        }

        foreach ($this->nilai as $kmmId => $nilaiBaris) {
            $nilaiAkhir = $matrix->hitungNilaiAkhirMahasiswa($this->columns, $nilaiBaris);

            KelasMkMahasiswa::query()
                ->whereKey($kmmId)
                ->update([
                    'nilai_angka' => $nilaiAkhir,
                    'nilai_huruf' => $matrix->hurufDariNilaiAkhir($nilaiAkhir),
                ]);
        }

        Notification::make()
            ->title('Tersimpan')
            ->success()
            ->send();

        $this->showKalkulasiBadge = true;
        $this->loadMatrix();
    }

    public function applyTempel(string $raw): void
    {
        if (! $this->matrixSiapClipboard()) {
            throw ValidationException::withMessages([
                'paste_data' => 'Matriks nilai belum siap untuk ditempel.',
            ]);
        }

        $kelasMk = KelasMk::query()->findOrFail($this->kelasMkId);
        Gate::authorize('inputNilai', $kelasMk);

        $result = app(InputNilaiMatrixClipboardService::class)->applyPaste(
            $raw,
            $this->rows,
            $this->columns,
            $this->nilai,
        );

        $this->nilai = $result['nilai'];

        $body = sprintf(
            '%d sel diperbarui pada %d mahasiswa. Klik Simpan untuk menyimpan ke database.',
            $result['applied_cells'],
            $result['matched_rows'],
        );

        if ($result['errors'] !== []) {
            $body .= ' Peringatan: '.implode(' ', array_slice($result['errors'], 0, 3));

            if (count($result['errors']) > 3) {
                $body .= ' …';
            }
        }

        Notification::make()
            ->title('Matriks diperbarui')
            ->body($body)
            ->success()
            ->send();
    }

    /**
     * Tombol "Capaian" pada tab Laporan > Hasil Analisis per Mahasiswa —
     * satu instance per baris mahasiswa, di-bind ke kmmId lewat ->arguments()
     * saat dirender (lihat input-nilai.blade.php).
     */
    public function capaianMahasiswaAction(): Action
    {
        return Action::make('capaianMahasiswa')
            ->label('Capaian')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->size('sm')
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

    /**
     * Tombol Salin matriks (badge 1) & Tempel dari Excel (badge 2) — rata
     * kiri, di dalam body card Penilaian sebelum tabel.
     *
     * @return list<Action>
     */
    public function getMatriksActionsKiri(): array
    {
        return [
            $this->salinNilaiAction(),
            $this->tempelNilaiAction(),
        ];
    }

    /**
     * Tombol Filter kolom — rata kanan, sebaris dengan tombol kiri di atas.
     *
     * @return list<Action>
     */
    public function getMatriksActionsKanan(): array
    {
        return [
            $this->filterKolomAsesmenAction(),
        ];
    }

    protected function filterKolomAsesmenAction(): Action
    {
        return Action::make('filterKolomAsesmen')
            ->label('Filter kolom')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->color('gray')
            ->visible(fn (): bool => count($this->columns) > 1)
            ->modalHeading('Filter kolom asesmen')
            ->modalDescription('Pilih asesmen yang ingin ditampilkan pada tabel penilaian. Hanya memengaruhi tampilan (dan Salin matriks), bukan nilai yang tersimpan.')
            ->modalSubmitActionLabel('Terapkan')
            ->modalCancelActionLabel('Batal')
            ->fillForm(fn (): array => ['kolom_terpilih' => $this->kolomTerpilih])
            ->schema([
                CheckboxList::make('kolom_terpilih')
                    ->hiddenLabel()
                    ->options(fn (): array => collect($this->columns)->pluck('asesmen', 'id')->all())
                    ->columns(2)
                    ->bulkToggleable()
                    ->required(),
            ])
            ->action(function (array $data): void {
                /** @var list<string> $kolomTerpilih */
                $kolomTerpilih = $data['kolom_terpilih'] ?? [];

                $this->kolomTerpilih = $kolomTerpilih;
            });
    }

    protected function salinNilaiAction(): Action
    {
        return Action::make('salinNilai')
            ->label('Salin matriks')
            ->icon(Heroicon::OutlinedClipboard)
            ->color('gray')
            ->badge('1')
            ->badgeColor('info')
            ->visible(fn (): bool => $this->matrixSiapClipboard())
            ->modalHeading('Salin matriks nilai')
            ->modalDescription('Salin ke Excel, edit nilai, lalu tempel kembali lewat tombol Tempel dari Excel.')
            ->modalSubmitActionLabel('Salin ke clipboard')
            ->modalCancelActionLabel('Tutup')
            ->schema([
                Placeholder::make('salin_petunjuk')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString(
                        '<p style="font-size:13px;opacity:.85;">'
                        .'Kolom: <strong>NIM</strong>, <strong>Nama</strong>, lalu tiap kolom kode asesmen '
                        .'(pemisah tab, siap tempel ke Excel).</p>'
                    )),
                Textarea::make('export_text')
                    ->label('Matriks nilai')
                    ->default(fn (): string => $this->teksSalinMatriks())
                    ->readOnly()
                    ->rows(12)
                    ->extraAttributes(['class' => 'font-mono text-xs']),
            ])
            ->action(function (): void {
                $teks = $this->teksSalinMatriks();

                if ($teks === '') {
                    Notification::make()
                        ->title('Tidak ada data')
                        ->body('Pilih kelas MK yang memiliki mahasiswa dan komponen penilaian.')
                        ->warning()
                        ->send();

                    return;
                }

                $this->js('navigator.clipboard.writeText('.json_encode($teks).')');

                Notification::make()
                    ->title('Disalin ke clipboard')
                    ->body('Tempel ke Excel, sesuaikan nilai, lalu impor lewat Tempel dari Excel.')
                    ->success()
                    ->send();
            });
    }

    protected function tempelNilaiAction(): Action
    {
        return Action::make('tempelNilai')
            ->label('Tempel dari Excel')
            ->icon(Heroicon::OutlinedClipboardDocument)
            ->color('gray')
            ->badge('2')
            ->badgeColor('warning')
            ->visible(fn (): bool => $this->matrixSiapClipboard())
            ->modalHeading('Tempel matriks nilai')
            ->modalDescription('Tempel blok sel dari Excel (termasuk baris header NIM/Nama).')
            ->modalSubmitActionLabel('Terapkan ke matriks')
            ->modalCancelActionLabel('Batal')
            ->schema([
                Placeholder::make('tempel_contoh')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => $this->contohTabelTempelHtml()),
                Placeholder::make('tempel_petunjuk')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString(
                        '<div style="font-size:13px;opacity:.85;">'
                        .'<p style="margin-bottom:8px;"><strong>Petunjuk:</strong></p>'
                        .'<ul style="margin:0;padding-left:18px;">'
                        .'<li>Gunakan format yang sama dengan hasil salin matriks (NIM, Nama, kolom nilai).</li>'
                        .'<li>Pemisah tab dari Excel didukung; pipe (<code>|</code>) juga diterima.</li>'
                        .'<li>Baris dicocokkan berdasarkan NIM; nilai kosong akan dikosongkan.</li>'
                        .'<li>Boleh menyertakan sebagian kolom asesmen saja — kolom yang tidak disertakan tidak akan berubah.</li>'
                        .'<li>Setelah diterapkan, klik <strong>Simpan</strong> pada halaman.</li>'
                        .'</ul></div>'
                    )),
                Textarea::make('paste_data')
                    ->label('Data yang ditempel')
                    ->rows(12)
                    ->required()
                    ->extraAttributes(['class' => 'font-mono text-xs']),
            ])
            ->action(function (array $data): void {
                $this->applyTempel((string) ($data['paste_data'] ?? ''));
            });
    }

    protected function teksSalinMatriks(): string
    {
        if (! $this->matrixSiapClipboard()) {
            return '';
        }

        return app(InputNilaiMatrixClipboardService::class)->exportTsv(
            $this->rows,
            $this->getColumnsTampilProperty(),
            $this->nilai,
        );
    }

    /**
     * Contoh tabel Excel (NIM, Nama, lalu kode asesmen) sebelum disalin —
     * dibangun dari kolom nyata kelas terpilih bila tersedia, agar contoh
     * yang ditampilkan relevan dengan asesmen mata kuliah ini.
     */
    protected function contohTabelTempelHtml(): HtmlString
    {
        $kodeAsesmen = collect($this->columns)->pluck('asesmen')->filter()->take(2)->values();

        if ($kodeAsesmen->isEmpty()) {
            $kodeAsesmen = collect(['Asesmen01', 'Asesmen02']);
        }

        $headerHtml = collect(['NIM', 'Nama'])
            ->concat($kodeAsesmen)
            ->map(fn (string $h): string => '<th style="border:1px solid rgba(128,128,128,.4);padding:4px 10px;'
                .'background:rgba(128,128,128,.15);font-weight:700;white-space:nowrap;">'.e($h).'</th>')
            ->implode('');

        $contohBaris = [
            ['242151111117', 'Siti Samrotul Lulu', '100', '85'],
            ['242151111118', 'Budi Santoso', '90', '78'],
        ];

        $bodyHtml = collect($contohBaris)
            ->map(fn (array $baris): string => '<tr>'.collect(array_slice($baris, 0, 2 + $kodeAsesmen->count()))
                ->map(fn (string $v): string => '<td style="border:1px solid rgba(128,128,128,.3);padding:4px 10px;'
                    .'white-space:nowrap;">'.e($v).'</td>')
                ->implode('').'</tr>')
            ->implode('');

        return new HtmlString(
            '<p style="font-size:12px;font-weight:600;margin-bottom:4px;opacity:.85;">'
            .'Contoh tabel di Excel sebelum disalin:</p>'
            .'<div style="overflow-x:auto;margin-bottom:10px;">'
            .'<table style="border-collapse:collapse;font-size:11px;">'
            .'<thead><tr>'.$headerHtml.'</tr></thead>'
            .'<tbody>'.$bodyHtml.'</tbody>'
            .'</table>'
            .'</div>'
        );
    }

    protected function matrixSiapClipboard(): bool
    {
        return $this->kelasMkId !== null
            && ! $this->penugasanBelumSelesai
            && $this->rows !== []
            && $this->columns !== [];
    }
}
