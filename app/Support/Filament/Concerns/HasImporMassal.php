<?php

namespace App\Support\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Impor massal via copy-paste (tab dari Excel atau pipe) dengan preview
 * berstatus baru/duplikat/invalid dan keputusan timpa/lewati untuk duplikat.
 *
 * Pemakai trait mendefinisikan kolom, validasi baris + deteksi duplikat,
 * serta cara membuat/memperbarui record.
 */
trait HasImporMassal
{
    use ClearsImporModalPreviewOnUnmount;
    use ReadsMountedActionData;

    /**
     * Salinan langsung nilai textarea "rows" saat ini, disinkronkan lewat
     * afterStateUpdated() pada Textarea (BUKAN lewat Get()/Set() — itu
     * terbukti rapuh karena Get::__invoke() sengaja skip menelusuri
     * anak-kontainer milik komponen yang sedang dievaluasi, sehingga
     * pembacaan state dari dalam hook komponen lain bisa jatuh ke
     * fallback yang tidak selalu menemukan state form dengan benar —
     * lihat makeImporMassalAction()). Harus public supaya ikut
     * disimpan/dipulihkan Livewire antar request.
     */
    public string $importMassalRowsLive = '';

    /**
     * Memo hasil parseImportRaw() dalam satu request (protected agar tidak
     * ikut disinkronkan Livewire).
     *
     * @var array<string, list<array<string, mixed>>>
     */
    protected array $importMassalParseCache = [];

    /**
     * Definisi kolom sesuai urutan tempel.
     *
     * @return list<array{key: string, label: string, wajib: bool}>
     */
    abstract protected function importColumns(): array;

    /**
     * Validasi entitas + deteksi duplikat untuk satu baris.
     *
     * Kembalikan status 'baru' | 'duplikat' | 'invalid', keterangan
     * (alasan invalid / info duplikat), existing_id bila duplikat, dan
     * dedup — kunci unik untuk mendeteksi duplikat antarbaris tempelan.
     *
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     * @return array{status: string, keterangan: string, existing_id?: ?string, dedup?: ?string}
     */
    abstract protected function resolveImportRow(array $data, array $context): array;

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    abstract protected function createImportRow(array $data, array $context): void;

    /**
     * @param  array<string, string>  $data
     * @param  array<string, mixed>  $context
     */
    protected function updateImportRow(string $existingId, array $data, array $context): void
    {
        // Default: timpa tidak didukung; override bila entitas mendukung.
    }

    protected function importSupportsOverwrite(): bool
    {
        return true;
    }

    /**
     * Field konteks tambahan pada langkah tempel data (mis. pilih unit).
     *
     * @return array<int, Component|Field>
     */
    protected function importContextComponents(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    protected function importContextKeys(): array
    {
        return [];
    }

    protected function importModalHeading(): string
    {
        return 'Impor massal';
    }

    /** Catatan tambahan pada petunjuk kolom. */
    protected function importHelperNote(): string
    {
        return '';
    }

    /**
     * Poin petunjuk tambahan khusus entitas (contoh, catatan domain, dll.).
     *
     * @return list<string>
     */
    protected function importInstructionsExtra(): array
    {
        return [];
    }

    /**
     * Definisi kolom impor; override dengan konteks bila kolom bergantung pilihan form (mis. kurikulum).
     *
     * @param  array<string, mixed>  $context
     * @return list<array{key: string, label: string, wajib: bool}>
     */
    protected function importColumnsForContext(array $context = []): array
    {
        return $this->importColumns();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected function importInstructionsExtraForContext(array $context = []): array
    {
        return $this->importInstructionsExtra();
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string>
     */
    protected function importExampleRowsForContext(array $context = []): array
    {
        return $this->importExampleRows();
    }

    /**
     * Baris contoh siap tempel (pisah | atau tab sesuai petunjuk).
     *
     * @return list<string>
     */
    protected function importExampleRows(): array
    {
        return [];
    }

    /**
     * Teks contoh siap tempel untuk placeholder textarea "Data yang ditempel".
     *
     * @param  array<string, mixed>  $context
     */
    protected function importExamplePlaceholder(array $context = []): ?string
    {
        $rows = $this->importExampleRowsForContext($context);

        if ($rows === []) {
            return null;
        }

        return implode("\n", $rows);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<string|HtmlString>
     */
    protected function importInstructionsList(array $context = []): array
    {
        $items = [];

        foreach ($this->importInstructionsExtraForContext($context) as $extra) {
            $items[] = $extra;
        }

        if ($this->importHelperNote() !== '') {
            $items[] = $this->importHelperNote();
        }

        return $items;
    }

    /**
     * Konversi indeks kolom (0-based) ke huruf kolom gaya Excel (A, B, ..., Z, AA, AB, ...).
     */
    protected function excelColumnLetter(int $index): string
    {
        $letter = '';
        $index++;

        while ($index > 0) {
            $remainder = ($index - 1) % 26;
            $letter = chr(65 + $remainder).$letter;
            $index = intdiv($index - 1, 26);
        }

        return $letter;
    }

    /**
     * Tabel bergaya lembar Excel (huruf kolom, nomor baris, baris label
     * berwarna sesuai wajib/opsional, dan baris contoh data) yang
     * menggantikan penjelasan teks urutan kolom / kolom wajib / opsional.
     *
     * @param  array<string, mixed>  $context
     */
    protected function renderImportContohExcelHtml(array $context = []): ?HtmlString
    {
        $columns = $this->importColumnsForContext($context);

        if ($columns === []) {
            return null;
        }

        $contohBaris = collect($this->importExampleRowsForContext($context))
            ->map(function (string $row) use ($columns): array {
                $separator = str_contains($row, "\t") ? "\t" : '|';
                $parts = array_map('trim', explode($separator, $row));

                return array_map(fn (int $i): string => $parts[$i] ?? '', array_keys($columns));
            })
            ->all();

        if ($contohBaris === []) {
            return null;
        }

        $chromeStyle = 'padding:2px 8px;background:rgba(128,128,128,.15);border:1px solid rgba(128,128,128,.25);font-weight:600;opacity:.7;';
        $cellStyle = 'padding:4px 8px;border:1px solid rgba(128,128,128,.25);white-space:nowrap;';

        $hurufKolom = '<td style="'.$chromeStyle.'"></td>';
        foreach (array_keys($columns) as $i) {
            $hurufKolom .= '<td style="'.$chromeStyle.'text-align:center;">'.$this->excelColumnLetter($i).'</td>';
        }

        $baris = [];
        // Baris <tr> "bersih" (tanpa kolom nomor/huruf dekoratif Excel-chrome),
        // dipakai sebagai payload HTML tombol salin di bawah — dikumpulkan di
        // loop yang sama supaya tidak menghitung ulang $contohBaris/$columns.
        $dataRowsHtmlBersih = [];

        $headerCells = '';
        foreach ($columns as $col) {
            $warna = $col['wajib'] ? '#b45309' : '#2563eb';
            $headerCells .= '<td style="'.$cellStyle.'font-weight:600;color:'.$warna.';">'.e($col['label']).'</td>';
        }
        $baris[] = $this->renderImportContohExcelRow(1, $headerCells, $chromeStyle);

        foreach ($contohBaris as $i => $parts) {
            $cells = '';
            foreach ($parts as $value) {
                $cells .= '<td style="'.$cellStyle.'">'.e($value).'</td>';
            }
            $baris[] = $this->renderImportContohExcelRow($i + 2, $cells, $chromeStyle);
            $dataRowsHtmlBersih[] = '<tr>'.$cells.'</tr>';
        }

        $tabel = '<div style="overflow-x:auto;margin-bottom:12px;">'
            .'<table style="border-collapse:collapse;font-size:12px;">'
            .'<tbody>'
            .'<tr>'.$hurufKolom.'</tr>'
            .implode('', $baris)
            .'</tbody></table></div>';

        $legendParts = [];

        if (collect($columns)->contains('wajib', true)) {
            $legendParts[] = '<span style="color:#b45309;font-weight:600;">Oranye</span> = kolom wajib';
        }

        if (collect($columns)->contains('wajib', false)) {
            $legendParts[] = '<span style="color:#2563eb;">Biru</span> = kolom opsional';
        }

        $legend = $legendParts === []
            ? ''
            : '<p class="mb-2 text-xs opacity-80">'.implode(' · ', $legendParts).'</p>';

        $tombolSalin = $this->renderImportCopyContohButton($columns, $contohBaris, $headerCells, $dataRowsHtmlBersih);

        return new HtmlString($legend.$tabel.$tombolSalin);
    }

    private function renderImportContohExcelRow(int $nomor, string $cells, string $chromeStyle): string
    {
        return '<tr><td style="'.$chromeStyle.'text-align:center;">'.$nomor.'</td>'.$cells.'</tr>';
    }

    /**
     * Tombol "Salin contoh data" di bawah tabel contoh — menyalin header +
     * baris contoh ke clipboard lewat Clipboard API multi-format
     * (text/html + text/plain), sehingga saat ditempel ke Excel/Google
     * Sheets warna teks header (oranye = wajib, biru = opsional) ikut
     * terjaga. text/plain (TSV) jadi isi bila target tempel tidak
     * memahami HTML; writeText() polos jadi fallback bila ClipboardItem
     * tidak tersedia (browser lama) atau navigator.clipboard tidak ada
     * (konteks tidak aman/non-HTTPS).
     *
     * Payload TSV & HTML ditaruh sebagai atribut data-copy-text /
     * data-copy-html, di-escape lewat {{ }} Blade (setara e(), dipakai
     * konsisten dengan sel-sel tabel lain di kelas ini) — browser otomatis
     * mendekode entity itu balik jadi teks asli saat dibaca lewat
     * dataset.copyText/dataset.copyHtml. Karena payload dinamis HANYA ada
     * di atribut (bukan disisipkan ke teks JS), handler @click bersifat
     * statis 100% dan didaftarkan sekali via x-init — idiom yang sama
     * dipakai silogyRekapAsesmen di modul lain.
     *
     * @param  list<array{key: string, label: string, wajib: bool}>  $columns
     * @param  list<list<string>>  $contohBaris
     * @param  list<string>  $dataRowsHtmlBersih
     */
    private function renderImportCopyContohButton(
        array $columns,
        array $contohBaris,
        string $headerCells,
        array $dataRowsHtmlBersih,
    ): string {
        $tsvBaris = [implode("\t", array_map(fn (array $col): string => $col['label'], $columns))];

        foreach ($contohBaris as $parts) {
            $tsvBaris[] = implode("\t", $parts);
        }

        $tsv = implode("\n", $tsvBaris);
        $htmlTabel = '<table><tbody><tr>'.$headerCells.'</tr>'.implode('', $dataRowsHtmlBersih).'</tbody></table>';

        // PENTING: string Blade di bawah harus TETAP statis (jangan pernah
        // sisipkan $tsv/$htmlTabel langsung ke dalamnya) — Blade::render()
        // meng-cache file view terkompilasi di storage/framework/views
        // berdasar hash isi string; kalau string ini berubah per baris
        // data, cache akan menumpuk tanpa batas. Nilai dinamis SELALU
        // lewat argumen $data kedua supaya cache-nya cuma satu file.
        return Blade::render(<<<'BLADE'
            <div class="mt-2" x-data x-init="
                window.silogySalinContohImpor = window.silogySalinContohImpor || function () {
                    return {
                        disalin: false,
                        async salin() {
                            const teks = this.$el.dataset.copyText ?? '';
                            const html = this.$el.dataset.copyHtml ?? '';
                            const clipboard = navigator.clipboard;

                            if (! clipboard) {
                                return;
                            }

                            try {
                                if (window.ClipboardItem && clipboard.write) {
                                    await clipboard.write([
                                        new ClipboardItem({
                                            'text/html': new Blob([html], { type: 'text/html' }),
                                            'text/plain': new Blob([teks], { type: 'text/plain' }),
                                        }),
                                    ]);
                                } else {
                                    await clipboard.writeText(teks);
                                }
                            } catch (err) {
                                try {
                                    await clipboard.writeText(teks);
                                } catch (err2) {
                                    return;
                                }
                            }

                            this.disalin = true;
                            setTimeout(() => { this.disalin = false; }, 1500);
                        },
                    };
                };
            ">
                <x-filament::button
                    type="button"
                    color="gray"
                    size="xs"
                    icon="heroicon-o-clipboard-document"
                    x-data="silogySalinContohImpor()"
                    x-on:click="salin()"
                    data-copy-text="{{ $tsv }}"
                    data-copy-html="{{ $htmlTabel }}"
                >
                    <span x-show="! disalin">Salin contoh data</span>
                    <span x-show="disalin" x-cloak>Tersalin &#10003;</span>
                </x-filament::button>
            </div>
            BLADE, [
            'tsv' => $tsv,
            'htmlTabel' => $htmlTabel,
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function renderImportGuideBox(array $context = []): HtmlString
    {
        $items = $this->importInstructionsList($context);

        $list = $items === []
            ? ''
            : '<ul class="list-disc space-y-1 ps-5">'
                .collect($items)
                    ->map(function (string|HtmlString $item): string {
                        $content = $item instanceof HtmlString ? $item->toHtml() : e($item);

                        return '<li>'.$content.'</li>';
                    })
                    ->join('')
                .'</ul>';

        return new HtmlString(
            '<div class="rounded-lg border border-primary-600/30 bg-primary-50 p-4 text-sm text-gray-700 dark:border-primary-500/30 dark:bg-primary-950/40 dark:text-gray-200">'
            .'<p class="mb-2 font-semibold">Petunjuk tempel data</p>'
            .($this->renderImportContohExcelHtml($context)?->toHtml() ?? '')
            .$list
            .'</div>'
        );
    }

    protected function importColumnsHelperText(): string
    {
        $penutup = 'Pratinjau muncul otomatis di bawah kotak tempel; tombol impor menyertainya begitu data terbaca.';

        if ($this->importExampleRows() !== []) {
            return 'Tempel baris data di bawah petunjuk (lihat contoh pada placeholder kotak di bawah). '.$penutup;
        }

        return 'Tempel baris data di bawah petunjuk. '.$penutup;
    }

    /**
     * Isi kotak tempel terkini. Property live dipakai lebih dulu, lalu state
     * form modal (mountedActions.{i}.data.rows) sebagai jaring aman: dengan
     * itu tombol "Impor sekarang" dan pilihan duplikat tetap dapat muncul
     * meski sinkronisasi live belum sempat berjalan (mis. dipanggil lewat
     * callAction pada test atau submit sebelum debounce selesai).
     */
    protected function importMassalRawTerkini(?Get $get = null): string
    {
        if ($this->importMassalRowsLive !== '') {
            return $this->importMassalRowsLive;
        }

        if ($get !== null && filled($raw = $get('rows'))) {
            return (string) $raw;
        }

        return (string) ($this->mountedActionDataTerkini()['rows'] ?? '');
    }

    /**
     * Konteks impor di luar schema (mis. saat menilai visibilitas tombol
     * submit modal) — dibaca dari state form modal karena Get() hanya
     * tersedia di dalam schema.
     *
     * @return array<string, mixed>
     */
    protected function importMassalContextTerkini(): array
    {
        return $this->importMassalContextDariData($this->mountedActionDataTerkini());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function importMassalContextDariData(array $data): array
    {
        $context = [];
        foreach ($this->importContextKeys() as $key) {
            $context[$key] = $data[$key] ?? null;
        }

        return $this->normalisasiImportContext($context);
    }

    /**
     * Kesempatan memaksa nilai konteks dari sisi server — dipakai bila
     * sasaran impor ditentukan sesi (mis. kurikulum yang dikerjakan) dan
     * bukan oleh field pada modal.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    protected function normalisasiImportContext(array $context): array
    {
        return $context;
    }

    /**
     * Hasil parsing untuk render (pratinjau, visibilitas radio duplikat, dan
     * visibilitas tombol submit) dihitung sekali per kombinasi data+konteks:
     * parseImportRaw() menembak query validasi per baris, sedangkan satu
     * render bisa membutuhkannya beberapa kali.
     *
     * @param  array<string, mixed>  $context
     * @return list<array{line: int, data: array<string, string>, status: string, keterangan: string, existing_id: ?string}>
     */
    protected function importMassalRowsTerparse(string $raw, array $context = []): array
    {
        $kunci = md5($raw.'|'.serialize($context));

        return $this->importMassalParseCache[$kunci] ??= $this->parseImportRaw($raw, $context);
    }

    /** Pratinjau berhasil dibaca: minimal satu baris terbaca dari kotak tempel. */
    protected function importMassalPratinjauSiap(?Get $get = null): bool
    {
        $raw = $this->importMassalRawTerkini($get);

        if (trim($raw) === '') {
            return false;
        }

        $context = $get !== null
            ? $this->importMassalContextDariGet($get)
            : $this->importMassalContextTerkini();

        return $this->importMassalRowsTerparse($raw, $context) !== [];
    }

    /** Ada baris duplikat pada pratinjau sehingga penanganannya perlu diputuskan. */
    protected function importMassalAdaDuplikat(?Get $get = null): bool
    {
        $raw = $this->importMassalRawTerkini($get);

        if (trim($raw) === '') {
            return false;
        }

        $context = $get !== null
            ? $this->importMassalContextDariGet($get)
            : $this->importMassalContextTerkini();

        return collect($this->importMassalRowsTerparse($raw, $context))
            ->contains('status', 'duplikat');
    }

    /**
     * @return array<string, mixed>
     */
    protected function importMassalContextDariGet(Get $get): array
    {
        $context = [];
        foreach ($this->importContextKeys() as $key) {
            $context[$key] = $get($key);
        }

        return $this->normalisasiImportContext($context);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{line: int, data: array<string, string>, status: string, keterangan: string, existing_id: ?string}>
     */
    public function parseImportRaw(string $raw, array $context = []): array
    {
        $columns = $this->importColumnsForContext($context);
        $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];

        $rows = [];
        $seen = [];

        foreach ($lines as $index => $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $separator = str_contains($line, "\t") ? "\t" : '|';
            $parts = array_map('trim', explode($separator, $line));

            $data = [];
            foreach ($columns as $i => $col) {
                $data[$col['key']] = $parts[$i] ?? '';
            }

            $row = [
                'line' => $index + 1,
                'data' => $data,
                'status' => 'baru',
                'keterangan' => '',
                'existing_id' => null,
            ];

            if (count($parts) > count($columns)) {
                $row['status'] = 'invalid';
                $row['keterangan'] = 'Jumlah kolom melebihi '.count($columns).'.';
                $rows[] = $row;

                continue;
            }

            $kosong = collect($columns)
                ->filter(fn (array $col): bool => $col['wajib'] && $data[$col['key']] === '')
                ->pluck('label');

            if ($kosong->isNotEmpty()) {
                $row['status'] = 'invalid';
                $row['keterangan'] = 'Kolom wajib belum diisi: '.$kosong->join(', ').'.';
                $rows[] = $row;

                continue;
            }

            $hasil = $this->resolveImportRow($data, $context);

            $row['status'] = $hasil['status'];
            $row['keterangan'] = $hasil['keterangan'];
            $row['existing_id'] = $hasil['existing_id'] ?? null;

            $dedup = $hasil['dedup'] ?? null;

            if ($row['status'] !== 'invalid' && $dedup !== null) {
                if (isset($seen[$dedup])) {
                    $row['status'] = 'invalid';
                    $row['keterangan'] = 'Duplikat dengan baris lain di data yang ditempel.';
                } else {
                    $seen[$dedup] = true;
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    /**
     * @param  array<string, mixed>  $context
     */
    public function renderImportPreview(string $raw, array $context = [], ?string $emptyMessage = null): HtmlString
    {
        $rows = $this->importMassalRowsTerparse($raw, $context);

        if ($rows === []) {
            $pesan = $emptyMessage
                ?? 'Belum ada data yang dapat dibaca. Tempel data pada kotak di atas terlebih dahulu.';

            return new HtmlString('<p class="text-sm opacity-80">'.$pesan.'</p>');
        }

        $jumlah = ['baru' => 0, 'duplikat' => 0, 'invalid' => 0];
        $body = '';
        $columns = $this->importColumnsForContext($context);

        foreach ($rows as $row) {
            $jumlah[$row['status']]++;

            [$badge, $warna] = match ($row['status']) {
                'baru' => ['Baru', '#16a34a'],
                'duplikat' => ['Duplikat', '#d97706'],
                default => ['Invalid', '#dc2626'],
            };

            $cells = '<td style="padding:4px 8px;white-space:nowrap;">'.$row['line'].'</td>';

            foreach ($columns as $col) {
                $cells .= '<td style="padding:4px 8px;">'.e($row['data'][$col['key']]).'</td>';
            }

            $cells .= '<td style="padding:4px 8px;white-space:nowrap;"><span style="font-weight:600;color:'.$warna.';">'.$badge.'</span>'
                .($row['keterangan'] !== '' ? '<br><span style="font-size:11px;opacity:.8;">'.e($row['keterangan']).'</span>' : '')
                .'</td>';

            $body .= '<tr style="border-top:1px solid rgba(128,128,128,.25);">'.$cells.'</tr>';
        }

        $ringkasan = sprintf(
            '<p class="text-sm" style="margin-bottom:8px;"><strong>%d baris terbaca:</strong> '
            .'<span style="color:#16a34a;font-weight:600;">%d baru</span> · '
            .'<span style="color:#d97706;font-weight:600;">%d duplikat</span> · '
            .'<span style="color:#dc2626;font-weight:600;">%d invalid</span>. '
            .'Baris invalid tidak akan diimpor.%s</p>',
            count($rows),
            $jumlah['baru'],
            $jumlah['duplikat'],
            $jumlah['invalid'],
            // Pilihan penanganan duplikat hanya dirender bila ada duplikat.
            $jumlah['duplikat'] > 0
                ? ' Nasib baris duplikat mengikuti pilihan di bawah.'
                : '',
        );

        $header = '<th style="padding:4px 8px;">Baris</th>';
        foreach ($columns as $col) {
            $header .= '<th style="padding:4px 8px;">'.e($col['label']).'</th>';
        }
        $header .= '<th style="padding:4px 8px;">Status</th>';

        $tabel = '<div style="overflow-x:auto;max-height:320px;overflow-y:auto;">'
            .'<table style="width:100%;font-size:12px;border-collapse:collapse;">'
            .'<thead><tr style="text-align:left;">'.$header.'</tr></thead>'
            .'<tbody>'.$body.'</tbody></table></div>';

        return new HtmlString($ringkasan.$tabel);
    }

    protected function makeImporMassalAction(): Action
    {
        $contextFromGet = fn (Get $get): array => $this->importMassalContextDariGet($get);

        $duplikatOptions = ['lewati' => 'Batal diinputkan (lewati duplikat)'];

        if ($this->importSupportsOverwrite()) {
            $duplikatOptions['timpa'] = 'Timpa data lama (perbarui)';
        }

        return Action::make('bulkImport')
            ->label('Impor massal')
            ->icon(Heroicon::OutlinedClipboardDocumentList)
            ->color('gray')
            ->modalHeading($this->importModalHeading())
            ->modalWidth(Width::SixExtraLarge)
            ->modalSubmitActionLabel('Impor sekarang')
            // Kosongkan pratinjau saat modal dibuka (jaring aman bila unmount
            // terlewat) DAN saat ditutup (lihat ClearsImporModalPreviewOnUnmount).
            // Tetap panggil $schema->fill() supaya default field tidak hilang
            // — lihat CanBeMounted::getMountUsing().
            ->mountUsing(function (Schema $schema): void {
                $this->kosongkanPreviewImporModal();
                $schema->fill();
            })
            ->after(function (): void {
                $this->kosongkanPreviewImporModal();
            })
            // Tombol impor baru muncul setelah pratinjau berhasil dibaca,
            // supaya tidak ada impor yang dijalankan tanpa melihat hasil
            // parsing lebih dulu.
            ->modalSubmitAction(fn (Action $action): Action|bool => $this->importMassalPratinjauSiap()
                ? $action
                : false)
            // Satu modal tanpa langkah: petunjuk, kotak tempel, pratinjau,
            // dan pilihan penanganan duplikat terlihat sekaligus bersama
            // tombol "Impor sekarang" di kaki modal.
            ->schema([
                ...$this->importContextComponents(),
                Placeholder::make('import_petunjuk')
                    ->hiddenLabel()
                    ->content(fn (Get $get): HtmlString => $this->renderImportGuideBox($contextFromGet($get))),
                Textarea::make('rows')
                    ->label('Data yang ditempel')
                    ->required()
                    ->rows(10)
                    ->live(debounce: 400)
                    ->afterStateUpdated(function (?string $state): void {
                        // Sengaja TIDAK lewat Get()/Set() — lihat catatan pada
                        // properti $importMassalRowsLive. $state adalah nilai
                        // textarea terbaru, dikirim langsung sebagai parameter
                        // closure, jadi tidak butuh pencarian path form.
                        $this->importMassalRowsLive = (string) $state;

                        // Filament v4 PartialsComponentHook memakai partial key
                        // berbeda antara request mountAction pertama kali
                        // ('action-modals') dan request update berikutnya pada
                        // action yang sama ('action-modals.{index}'). DOM
                        // browser masih membawa wire:partial dari key lama,
                        // sehingga partials.js gagal menemukan elemen match dan
                        // diam-diam membuang HTML preview yang baru dihitung
                        // (tanpa error/console warning). Paksa full render agar
                        // morph memakai jalur Livewire standar, bukan sistem
                        // partial custom Filament yang kena bug key-mismatch itu.
                        if (method_exists($this, 'forceRender')) {
                            $this->forceRender();
                        }
                    })
                    ->placeholder(fn (Get $get): ?string => $this->importExamplePlaceholder($contextFromGet($get)))
                    ->helperText($this->importColumnsHelperText()),
                Placeholder::make('preview_live')
                    ->hiddenLabel()
                    ->content(fn (Get $get): HtmlString => $this->renderImportPreview(
                        $this->importMassalRawTerkini($get),
                        $contextFromGet($get),
                        emptyMessage: 'Pratinjau akan muncul di sini setelah data ditempel, '
                            .'berikut tombol impornya.',
                    )),
                // Hanya relevan bila pratinjau memang menemukan duplikat;
                // tanpa duplikat, runImport() tetap memakai default 'lewati'.
                Radio::make('mode_duplikat')
                    ->label('Tindakan untuk data duplikat')
                    ->options($duplikatOptions)
                    ->default('lewati')
                    ->required()
                    ->visible(fn (Get $get): bool => $this->importMassalAdaDuplikat($get)),
            ])
            ->action(function (array $data): void {
                $context = $this->importMassalContextDariData($data);

                $this->runImport(
                    $this->importMassalRowsLive !== '' ? $this->importMassalRowsLive : (string) ($data['rows'] ?? ''),
                    (string) ($data['mode_duplikat'] ?? 'lewati'),
                    $context,
                );
            });
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function runImport(string $raw, string $modeDuplikat, array $context = []): void
    {
        $rows = $this->parseImportRaw($raw, $context);

        $dibuat = 0;
        $diperbarui = 0;
        $dilewati = 0;
        $gagal = [];

        DB::transaction(function () use ($rows, $modeDuplikat, $context, &$dibuat, &$diperbarui, &$dilewati, &$gagal): void {
            foreach ($rows as $row) {
                if ($row['status'] === 'invalid') {
                    $gagal[] = "Baris {$row['line']}: {$row['keterangan']}";

                    continue;
                }

                if ($row['status'] === 'duplikat') {
                    if ($modeDuplikat !== 'timpa' || ! $this->importSupportsOverwrite()) {
                        $dilewati++;

                        continue;
                    }

                    if (blank($row['existing_id'])) {
                        $gagal[] = "Baris {$row['line']}: data lama tidak ditemukan.";

                        continue;
                    }

                    $this->updateImportRow($row['existing_id'], $row['data'], $context);
                    $diperbarui++;

                    continue;
                }

                $this->createImportRow($row['data'], $context);
                $dibuat++;
            }
        });

        $ringkasan = sprintf(
            'Berhasil dibuat: %d · Diperbarui (timpa): %d · Dilewati (duplikat): %d · Gagal: %d',
            $dibuat,
            $diperbarui,
            $dilewati,
            count($gagal),
        );

        $detailGagal = $gagal === []
            ? ''
            : "\n".implode("\n", array_slice($gagal, 0, 8)).(count($gagal) > 8 ? "\n…" : '');

        $notification = Notification::make()
            ->title('Impor selesai')
            ->body($ringkasan.$detailGagal);

        if ($dibuat + $diperbarui > 0 && $gagal === []) {
            $notification->success();
        } elseif ($dibuat + $diperbarui > 0) {
            $notification->warning()->persistent();
        } else {
            $notification->danger()->persistent();
        }

        $notification->send();
    }
}
