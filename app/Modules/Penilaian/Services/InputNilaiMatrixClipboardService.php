<?php

namespace App\Modules\Penilaian\Services;

use Illuminate\Validation\ValidationException;

class InputNilaiMatrixClipboardService
{
    /**
     * Ekspor matriks nilai sebagai TSV (tab) siap tempel ke Excel.
     *
     * @param  list<array{id: string, nim: string, nama: string}>  $rows
     * @param  list<array{id: string, label: string}>  $columns
     * @param  array<string, array<string, string|null>>  $nilai
     */
    public function exportTsv(array $rows, array $columns, array $nilai): string
    {
        $header = array_merge(
            ['NIM', 'Nama'],
            array_map(fn (array $column): string => $column['label'], $columns),
        );

        $lines = [implode("\t", $header)];

        foreach ($rows as $row) {
            $cells = [$row['nim'], $row['nama']];

            foreach ($columns as $column) {
                $value = $nilai[$row['id']][$column['id']] ?? null;
                $cells[] = $value === null || $value === '' ? '' : (string) $value;
            }

            $lines[] = implode("\t", $cells);
        }

        return implode("\n", $lines);
    }

    /**
     * Terapkan data tempel (tab/pipe) ke matriks nilai.
     *
     * @param  list<array{id: string, nim: string, nama: string}>  $rows
     * @param  list<array{id: string, label: string}>  $columns
     * @param  array<string, array<string, string|null>>  $currentNilai
     * @param  list<string>  $kolomDitolak  label kolom yang tidak boleh ditimpa (mis. kode asesmen yang
     *                                      sedang dirinci per Sub-CPMK); bila muncul di kepala tempel, dilaporkan
     *                                      sebagai galat dan nilainya diabaikan
     * @param  bool  $wajibJudulKolom  true bila ada asesmen yang dirinci: kolom tempel tidak
     *                                 boleh dicocokkan menurut posisi (lembar lama "UTS, UAS"
     *                                 akan mendarat di sub-kolom yang salah), jadi tempel tanpa
     *                                 baris judul kolom yang dikenali ditolak
     * @return array{
     *     nilai: array<string, array<string, string|null>>,
     *     applied_cells: int,
     *     matched_rows: int,
     *     errors: list<string>
     * }
     */
    public function applyPaste(
        string $raw,
        array $rows,
        array $columns,
        array $currentNilai,
        array $kolomDitolak = [],
        bool $wajibJudulKolom = false,
    ): array {
        $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
        $lines = array_values(array_filter(
            $lines,
            fn (string $line): bool => trim($line) !== '',
        ));

        if ($lines === []) {
            throw ValidationException::withMessages([
                'paste_data' => 'Belum ada data yang ditempel.',
            ]);
        }

        $separator = $this->detectSeparator($lines[0]);
        $parsedLines = array_map(
            fn (string $line): array => array_map('trim', explode($separator, $line)),
            $lines,
        );

        $hasHeader = $this->isHeaderRow($parsedLines[0]);
        $headerCells = $hasHeader ? $parsedLines[0] : null;
        $dataLines = $hasHeader ? array_slice($parsedLines, 1) : $parsedLines;

        if ($dataLines === []) {
            throw ValidationException::withMessages([
                'paste_data' => 'Tidak ada baris data setelah header.',
            ]);
        }

        $errors = [];
        $adaKolomDitolak = false;

        foreach ($kolomDitolak as $label) {
            if ($headerCells !== null && in_array($this->normalizeHeader($label), array_map($this->normalizeHeader(...), $headerCells), true)) {
                $adaKolomDitolak = true;
                $errors[] = "Kolom \"{$label}\" tidak diterapkan: asesmen ini dirinci per Sub-CPMK, isi kolom per Sub-CPMK (berlabel {$label}/kode Sub-CPMK).";
            }
        }

        $columnIndexes = $this->resolveColumnIndexes($headerCells, $columns, $adaKolomDitolak, $wajibJudulKolom);

        if ($columnIndexes === null) {
            throw ValidationException::withMessages([
                'paste_data' => 'Sertakan baris judul kolom saat ada asesmen yang dirinci per Sub-CPMK.',
            ]);
        }
        $rowsByNim = collect($rows)->keyBy(
            fn (array $row): string => $this->normalizeNim($row['nim']),
        );

        $nilai = $currentNilai;
        $appliedCells = 0;
        $matchedRows = 0;

        foreach ($dataLines as $lineIndex => $cells) {
            $lineNumber = $hasHeader ? $lineIndex + 2 : $lineIndex + 1;
            $nim = $this->normalizeNim($cells[0] ?? '');

            $row = $nim !== '' ? $rowsByNim->get($nim) : null;

            if ($row === null) {
                $row = $rows[$lineIndex] ?? null;

                if ($row === null) {
                    $errors[] = "Baris {$lineNumber}: mahasiswa tidak dikenali (NIM: ".($cells[0] ?? '—').').';

                    continue;
                }
            }

            $matchedRows++;
            $kmmId = $row['id'];

            if (! isset($nilai[$kmmId])) {
                $nilai[$kmmId] = [];
            }

            foreach ($columnIndexes as $columnId => $cellIndex) {
                $rawValue = $cells[$cellIndex] ?? '';

                if ($rawValue === '') {
                    $nilai[$kmmId][$columnId] = null;
                    $appliedCells++;

                    continue;
                }

                $numeric = $this->parseNumeric($rawValue);

                if ($numeric === null) {
                    $errors[] = "Baris {$lineNumber}, kolom ".($cellIndex + 1).": nilai \"{$rawValue}\" tidak valid (harus 0–100).";

                    continue;
                }

                if ($numeric < 0 || $numeric > 100) {
                    $errors[] = "Baris {$lineNumber}, kolom ".($cellIndex + 1).': nilai harus antara 0 dan 100.';

                    continue;
                }

                $nilai[$kmmId][$columnId] = $this->formatNilai($numeric);
                $appliedCells++;
            }
        }

        if ($matchedRows === 0) {
            throw ValidationException::withMessages([
                'paste_data' => $errors !== []
                    ? implode(' ', $errors)
                    : 'Tidak ada baris mahasiswa yang cocok dengan data tempel.',
            ]);
        }

        return [
            'nilai' => $nilai,
            'applied_cells' => $appliedCells,
            'matched_rows' => $matchedRows,
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<string>  $lines
     */
    private function detectSeparator(string $firstLine): string
    {
        if (str_contains($firstLine, "\t")) {
            return "\t";
        }

        if (str_contains($firstLine, '|')) {
            return '|';
        }

        return "\t";
    }

    /**
     * @param  list<string>  $cells
     */
    private function isHeaderRow(array $cells): bool
    {
        $first = mb_strtolower(trim($cells[0] ?? ''));

        return in_array($first, ['nim', 'no', 'no.', 'no nim', 'nomor induk'], true);
    }

    /**
     * @param  list<string>|null  $headerCells
     * @param  list<array{id: string, label: string}>  $columns
     * @return array<string, int>|null null = perlu jatuh ke urutan posisi padahal $wajibJudulKolom
     */
    private function resolveColumnIndexes(?array $headerCells, array $columns, bool $adaKolomDitolak = false, bool $wajibJudulKolom = false): ?array
    {
        $indexes = [];

        if ($headerCells === null) {
            if ($wajibJudulKolom) {
                return null;
            }

            foreach ($columns as $offset => $column) {
                $indexes[$column['id']] = $offset + 2;
            }

            return $indexes;
        }

        // Kolom kini dilabeli hanya berdasarkan kode asesmen, jadi beberapa
        // kolom (Sub-CPMK berbeda pada asesmen yang sama) bisa berbagi label
        // yang sama — dicocokkan berurutan per grup label, bukan selalu ke
        // kemunculan pertama, agar tiap kolom tetap dapat indeks tempel unik.
        $headerIndexesByLabel = [];

        foreach ($headerCells as $index => $header) {
            $headerIndexesByLabel[$this->normalizeHeader($header)][] = $index;
        }

        foreach ($columns as $column) {
            $label = $this->normalizeHeader($column['label']);

            if (empty($headerIndexesByLabel[$label])) {
                continue;
            }

            $indexes[$column['id']] = array_shift($headerIndexesByLabel[$label]);
        }

        // Kepala yang hanya memuat kolom ditolak tidak boleh jatuh ke urutan
        // posisi: nilainya akan salah mendarat di kolom lain.
        if ($indexes !== [] || $adaKolomDitolak) {
            return $indexes;
        }

        if ($wajibJudulKolom) {
            return null;
        }

        foreach ($columns as $offset => $column) {
            $indexes[$column['id']] = $offset + 2;
        }

        return $indexes;
    }

    private function normalizeHeader(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function normalizeNim(string $nim): string
    {
        return mb_strtolower(trim($nim));
    }

    private function parseNumeric(string $value): ?float
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($value));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function formatNilai(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
