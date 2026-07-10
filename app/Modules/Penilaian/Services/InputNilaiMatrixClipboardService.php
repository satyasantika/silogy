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

        $columnIndexes = $this->resolveColumnIndexes($headerCells, $columns);
        $rowsByNim = collect($rows)->keyBy(
            fn (array $row): string => $this->normalizeNim($row['nim']),
        );

        $nilai = $currentNilai;
        $appliedCells = 0;
        $matchedRows = 0;
        $errors = [];

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
     * @return array<string, int>
     */
    private function resolveColumnIndexes(?array $headerCells, array $columns): array
    {
        $indexes = [];

        if ($headerCells === null) {
            foreach ($columns as $offset => $column) {
                $indexes[$column['id']] = $offset + 2;
            }

            return $indexes;
        }

        $labelsById = collect($columns)->keyBy('id');
        $normalizedHeaders = collect($headerCells)
            ->map(fn (string $header): string => $this->normalizeHeader($header));

        foreach ($columns as $column) {
            $label = $this->normalizeHeader($column['label']);
            $index = $normalizedHeaders->search($label);

            if ($index === false) {
                continue;
            }

            $indexes[$column['id']] = $index;
        }

        if ($indexes !== []) {
            return $indexes;
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
