<?php

namespace App\Modules\Penilaian\Filament\Pages;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Penilaian\Filament\Pages\Concerns\HasKelasMkDosenPicker;
use App\Modules\Penilaian\Filament\Pages\Concerns\HasLaporanKelasMk;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Policies\InputNilaiPolicy;
use App\Modules\Penilaian\Services\InputNilaiMatrixClipboardService;
use App\Modules\Penilaian\Services\PenilaianMatrixService;
use App\Support\Filament\NavigationGroupPeran;
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
    use HasLaporanKelasMk;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPencilSquare;

    protected static ?string $navigationLabel = 'Input Nilai';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return NavigationGroupPeran::resolve('Pengampu MK');
    }

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'penilaian/input-nilai';

    protected static ?string $title = 'Input Nilai';

    protected string $view = 'filament.modules.penilaian.pages.input-nilai';

    /**
     * Subset id kolom (KomponenPenilaian) yang dipilih untuk ditampilkan —
     * filter tampilan (ikut mempersempit Salin matriks), tidak memengaruhi
     * data yang disimpan lewat Simpan/Tempel dari Excel.
     *
     * @var list<string>
     */
    public array $kolomTerpilih = [];

    public bool $showKalkulasiBadge = false;

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

    public function loadMatrix(): void
    {
        $this->resetDataLaporan();

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

        $this->muatDataLaporan($kelasMk);

        // Reset filter tampilan ke "semua kolom" hanya saat pertama kali
        // dimuat atau saat berpindah kelas/MK (kolom terpilih lama tidak
        // valid lagi) — filter yang masih relevan (mis. setelah Simpan atau
        // Tempel dari Excel memuat ulang matriks) tetap dipertahankan.
        $idKolom = collect($this->columns)->pluck('id')->all();

        if ($this->kolomTerpilih === [] || array_diff($this->kolomTerpilih, $idKolom) !== []) {
            $this->kolomTerpilih = $idKolom;
        }
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
