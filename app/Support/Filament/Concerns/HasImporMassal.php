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
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
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

        return new HtmlString($legend.$tabel);
    }

    private function renderImportContohExcelRow(int $nomor, string $cells, string $chromeStyle): string
    {
        return '<tr><td style="'.$chromeStyle.'text-align:center;">'.$nomor.'</td>'.$cells.'</tr>';
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
        if ($this->importExampleRows() !== []) {
            return 'Tempel baris data di bawah petunjuk (lihat contoh pada placeholder kotak di bawah). Pratinjau tersedia pada langkah berikutnya.';
        }

        return 'Tempel baris data di bawah petunjuk. Pratinjau tersedia pada langkah berikutnya.';
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
    public function renderImportPreview(string $raw, array $context = []): HtmlString
    {
        $rows = $this->parseImportRaw($raw, $context);

        if ($rows === []) {
            return new HtmlString('<p class="text-sm">Belum ada data yang dapat dibaca. Kembali ke langkah sebelumnya dan tempel data terlebih dahulu.</p>');
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
            .'Baris invalid tidak akan diimpor; nasib baris duplikat mengikuti pilihan di bawah.</p>',
            count($rows),
            $jumlah['baru'],
            $jumlah['duplikat'],
            $jumlah['invalid'],
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
        $contextKeys = $this->importContextKeys();

        $contextFromGet = function (Get $get) use ($contextKeys): array {
            $context = [];
            foreach ($contextKeys as $key) {
                $context[$key] = $get($key);
            }

            return $context;
        };

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
            ->modalSubmitAction(false)
            ->schema([
                Wizard::make([
                    Step::make('Tempel data')
                        ->icon(Heroicon::OutlinedClipboard)
                        ->schema([
                            ...$this->importContextComponents(),
                            Placeholder::make('import_petunjuk')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => $this->renderImportGuideBox($contextFromGet($get))),
                            Textarea::make('rows')
                                ->label('Data yang ditempel')
                                ->required()
                                ->rows(10)
                                ->live()
                                ->placeholder(fn (Get $get): ?string => $this->importExamplePlaceholder($contextFromGet($get)))
                                ->helperText($this->importColumnsHelperText()),
                        ]),
                    Step::make('Preview & konfirmasi')
                        ->icon(Heroicon::OutlinedEye)
                        ->schema([
                            Placeholder::make('preview')
                                ->hiddenLabel()
                                ->content(fn (Get $get): HtmlString => $this->renderImportPreview(
                                    (string) $get('rows'),
                                    $contextFromGet($get),
                                )),
                            Radio::make('mode_duplikat')
                                ->label('Tindakan untuk data duplikat')
                                ->options($duplikatOptions)
                                ->default('lewati')
                                ->required(),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render(
                        '<x-filament::button type="submit" icon="heroicon-m-arrow-down-tray">Impor sekarang</x-filament::button>'
                    ))),
            ])
            ->action(function (array $data) use ($contextKeys): void {
                $context = [];
                foreach ($contextKeys as $key) {
                    $context[$key] = $data[$key] ?? null;
                }

                $this->runImport(
                    (string) $data['rows'],
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
