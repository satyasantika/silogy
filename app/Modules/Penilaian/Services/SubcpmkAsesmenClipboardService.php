<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Impor massal copy-paste untuk matriks bobot interaksi Sub-CPMK ↔ Asesmen.
 * Format wajib menyertakan header (Kode Asesmen, lalu satu atau beberapa
 * kolom kode Sub-CPMK dinamis) karena kolom dicocokkan berdasarkan kode,
 * bukan posisi — beda dari impor baris-per-record biasa.
 */
class SubcpmkAsesmenClipboardService
{
    /**
     * @param  Collection<int, KomponenPenilaian>  $asesmen
     * @param  Collection<int, Subcpmk>  $subcpmks
     * @param  Collection<string, float>  $bobots  kunci "asesmenId/subcpmkId"
     */
    public function exportTsv(Collection $asesmen, Collection $subcpmks, Collection $bobots): string
    {
        $header = array_merge(
            ['Kode Asesmen'],
            $subcpmks->pluck('kode')->all(),
        );

        $lines = [implode("\t", $header)];

        foreach ($asesmen as $komponen) {
            if (blank($komponen->kode)) {
                continue;
            }

            $cells = [$komponen->kode];

            foreach ($subcpmks as $subcpmk) {
                $value = $bobots[$komponen->id.'/'.$subcpmk->id] ?? null;
                $cells[] = $value === null ? '' : $this->formatBobot((float) $value);
            }

            $lines[] = implode("\t", $cells);
        }

        return implode("\n", $lines);
    }

    /**
     * Parse + validasi tempelan tanpa menerapkan perubahan (untuk preview).
     *
     * @param  Collection<int, KomponenPenilaian>  $asesmen
     * @param  Collection<int, Subcpmk>  $subcpmks
     * @param  Collection<string, float>  $currentBobots  kunci "asesmenId/subcpmkId"
     * @return array{
     *     kolom: list<array{index: int, subcpmk_id: string, kode_subcpmk: string}>,
     *     kolom_tidak_dikenali: list<string>,
     *     baris: list<array{
     *         baris_ke: int,
     *         kode_asesmen_input: string,
     *         komponen_id: string|null,
     *         komponen_nama: string|null,
     *         dikenali: bool,
     *         sel: list<array{subcpmk_id: string, kode_subcpmk: string, raw: string, status: string, bobot: float|null, keterangan: string}>
     *     }>,
     *     ringkasan: array{baris_dikenali: int, baris_tidak_dikenali: int, sel_update: int, sel_hapus: int, sel_invalid: int},
     *     errors: list<string>
     * }
     */
    public function parsePaste(
        string $raw,
        Collection $asesmen,
        Collection $subcpmks,
        Collection $currentBobots,
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

        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'paste_data' => 'Data harus menyertakan baris header dan minimal satu baris data.',
            ]);
        }

        $separator = str_contains($lines[0], "\t") ? "\t" : '|';
        $parsedLines = array_map(
            fn (string $line): array => array_map('trim', explode($separator, $line)),
            $lines,
        );

        $headerCells = $parsedLines[0];

        if (count($headerCells) < 2) {
            throw ValidationException::withMessages([
                'paste_data' => 'Baris header harus menyertakan kolom "Kode Asesmen" dan minimal satu kolom kode Sub-CPMK.',
            ]);
        }

        $subcpmkByKode = $subcpmks->keyBy(fn (Subcpmk $subcpmk): string => $this->normalize($subcpmk->kode));

        $kolom = [];
        $kolomTidakDikenali = [];

        foreach (array_slice($headerCells, 1) as $offset => $headerCell) {
            $subcpmk = $headerCell !== '' ? $subcpmkByKode->get($this->normalize($headerCell)) : null;

            if (! $subcpmk instanceof Subcpmk) {
                if ($headerCell !== '') {
                    $kolomTidakDikenali[] = $headerCell;
                }

                continue;
            }

            $kolom[] = [
                'index' => $offset + 1,
                'subcpmk_id' => $subcpmk->id,
                'kode_subcpmk' => $subcpmk->kode,
            ];
        }

        if ($kolom === []) {
            throw ValidationException::withMessages([
                'paste_data' => 'Tidak ada kolom kode Sub-CPMK yang dikenali pada header.',
            ]);
        }

        $asesmenByKode = $asesmen
            ->filter(fn (KomponenPenilaian $komponen): bool => filled($komponen->kode))
            ->keyBy(fn (KomponenPenilaian $komponen): string => $this->normalize((string) $komponen->kode));

        $baris = [];
        $ringkasan = [
            'baris_dikenali' => 0,
            'baris_tidak_dikenali' => 0,
            'sel_update' => 0,
            'sel_hapus' => 0,
            'sel_invalid' => 0,
        ];
        $errors = [];

        foreach (array_slice($parsedLines, 1) as $lineIndex => $cells) {
            $lineNumber = $lineIndex + 2;
            $kodeInput = $cells[0] ?? '';
            $komponen = $kodeInput !== '' ? $asesmenByKode->get($this->normalize($kodeInput)) : null;

            if (! $komponen instanceof KomponenPenilaian) {
                $ringkasan['baris_tidak_dikenali']++;
                $errors[] = "Baris {$lineNumber}: kode asesmen \"{$kodeInput}\" tidak dikenali.";

                $baris[] = [
                    'baris_ke' => $lineNumber,
                    'kode_asesmen_input' => $kodeInput,
                    'komponen_id' => null,
                    'komponen_nama' => null,
                    'dikenali' => false,
                    'sel' => [],
                ];

                continue;
            }

            $ringkasan['baris_dikenali']++;
            $selBaris = [];

            foreach ($kolom as $k) {
                $rawValue = $cells[$k['index']] ?? '';
                $existing = $currentBobots[$komponen->id.'/'.$k['subcpmk_id']] ?? null;

                if ($rawValue === '') {
                    $adaNilaiLama = $existing !== null && (float) $existing > 0;
                    $status = $adaNilaiLama ? 'hapus' : 'kosong';

                    if ($status === 'hapus') {
                        $ringkasan['sel_hapus']++;
                    }

                    $selBaris[] = [
                        'subcpmk_id' => $k['subcpmk_id'],
                        'kode_subcpmk' => $k['kode_subcpmk'],
                        'raw' => '',
                        'status' => $status,
                        'bobot' => null,
                        'keterangan' => '',
                    ];

                    continue;
                }

                $numeric = $this->parseNumeric($rawValue);
                $bobotAsesmen = (float) $komponen->bobot;

                if ($numeric === null || $numeric < 0 || $numeric > $bobotAsesmen) {
                    $ringkasan['sel_invalid']++;
                    $keterangan = $numeric === null
                        ? "nilai \"{$rawValue}\" bukan angka"
                        : "nilai harus antara 0 dan bobot Asesmen ini ({$bobotAsesmen})";
                    $errors[] = "Baris {$lineNumber}, kolom {$k['kode_subcpmk']}: {$keterangan}.";

                    $selBaris[] = [
                        'subcpmk_id' => $k['subcpmk_id'],
                        'kode_subcpmk' => $k['kode_subcpmk'],
                        'raw' => $rawValue,
                        'status' => 'invalid',
                        'bobot' => null,
                        'keterangan' => $keterangan,
                    ];

                    continue;
                }

                $ringkasan['sel_update']++;

                $selBaris[] = [
                    'subcpmk_id' => $k['subcpmk_id'],
                    'kode_subcpmk' => $k['kode_subcpmk'],
                    'raw' => $rawValue,
                    'status' => 'update',
                    'bobot' => $numeric,
                    'keterangan' => '',
                ];
            }

            $selBaris = $this->terapkanBatasBaris($selBaris, $komponen, $currentBobots, $kolom, $lineNumber, $ringkasan, $errors);

            $baris[] = [
                'baris_ke' => $lineNumber,
                'kode_asesmen_input' => $kodeInput,
                'komponen_id' => $komponen->id,
                'komponen_nama' => $komponen->nama,
                'dikenali' => true,
                'sel' => $selBaris,
            ];
        }

        if ($ringkasan['baris_dikenali'] === 0) {
            throw ValidationException::withMessages([
                'paste_data' => 'Tidak ada baris kode asesmen yang dikenali pada data yang ditempel.',
            ]);
        }

        return [
            'kolom' => $kolom,
            'kolom_tidak_dikenali' => $kolomTidakDikenali,
            'baris' => $baris,
            'ringkasan' => $ringkasan,
            'errors' => $errors,
        ];
    }

    /**
     * Terapkan hasil parsePaste() yang valid ke database.
     *
     * @param  array<string, mixed>  $preview  hasil parsePaste()
     * @return array{diperbarui: int, dihapus: int, gagal: int}
     */
    public function terapkan(array $preview): array
    {
        $diperbarui = 0;
        $dihapus = 0;
        $gagal = 0;

        DB::transaction(function () use ($preview, &$diperbarui, &$dihapus, &$gagal): void {
            foreach ($preview['baris'] as $baris) {
                if (! $baris['dikenali']) {
                    continue;
                }

                $komponen = KomponenPenilaian::query()->find($baris['komponen_id']);

                if (! $komponen instanceof KomponenPenilaian) {
                    $gagal += count(array_filter(
                        $baris['sel'],
                        fn (array $sel): bool => in_array($sel['status'], ['update', 'hapus'], true),
                    ));

                    continue;
                }

                foreach ($baris['sel'] as $sel) {
                    if ($sel['status'] === 'update') {
                        SubcpmkKomponenPenilaian::query()->updateOrCreate(
                            [
                                'komponen_penilaian_id' => $komponen->id,
                                'subcpmk_id' => $sel['subcpmk_id'],
                            ],
                            [
                                'bobot' => (float) $sel['bobot'],
                                'semester_id' => $komponen->semester_id,
                            ],
                        );
                        $diperbarui++;

                        continue;
                    }

                    if ($sel['status'] === 'hapus') {
                        SubcpmkKomponenPenilaian::query()
                            ->where('komponen_penilaian_id', $komponen->id)
                            ->where('subcpmk_id', $sel['subcpmk_id'])
                            ->get()
                            ->each(fn (SubcpmkKomponenPenilaian $pivot) => $pivot->delete());
                        $dihapus++;
                    }
                }
            }
        });

        return ['diperbarui' => $diperbarui, 'dihapus' => $dihapus, 'gagal' => $gagal];
    }

    /**
     * Validasi TINGKAT BARIS: total bobot satu Asesmen (sel "update" yang
     * baru ditempel + pivot lama milik Sub-CPMK lain yang TIDAK ikut jadi
     * kolom pada tempelan ini) tidak boleh melebihi bobot Asesmen itu
     * sendiri — validasi per sel saja tidak cukup karena beberapa sel pada
     * baris yang sama bisa lolos validasi individual tapi jumlahnya
     * melebihi kapasitas. Bila melebihi, seluruh sel "update" pada baris
     * itu diturunkan jadi "invalid" (tidak ada yang diterapkan).
     *
     * @param  list<array{subcpmk_id: string, kode_subcpmk: string, raw: string, status: string, bobot: float|null, keterangan: string}>  $selBaris
     * @param  list<array{index: int, subcpmk_id: string, kode_subcpmk: string}>  $kolom
     * @param  Collection<string, float>  $currentBobots
     * @param  array{baris_dikenali: int, baris_tidak_dikenali: int, sel_update: int, sel_hapus: int, sel_invalid: int}  $ringkasan
     * @param  list<string>  $errors
     * @return list<array{subcpmk_id: string, kode_subcpmk: string, raw: string, status: string, bobot: float|null, keterangan: string}>
     */
    private function terapkanBatasBaris(
        array $selBaris,
        KomponenPenilaian $komponen,
        Collection $currentBobots,
        array $kolom,
        int $lineNumber,
        array &$ringkasan,
        array &$errors,
    ): array {
        $totalBaru = collect($selBaris)->where('status', 'update')->sum('bobot');

        $kolomSubcpmkIds = collect($kolom)->pluck('subcpmk_id')->all();

        $existingUntouched = $currentBobots
            ->filter(function (float $ignored, string $key) use ($komponen, $kolomSubcpmkIds): bool {
                [$komponenId, $subcpmkId] = explode('/', $key, 2);

                return $komponenId === $komponen->id && ! in_array($subcpmkId, $kolomSubcpmkIds, true);
            })
            ->sum();

        $totalBaris = (float) $existingUntouched + (float) $totalBaru;
        $bobotAsesmen = (float) $komponen->bobot;

        if ($totalBaris <= $bobotAsesmen + 0.01) {
            return $selBaris;
        }

        $errors[] = sprintf(
            'Baris %d: total bobot (%s) melebihi bobot Asesmen "%s" (%s) — seluruh sel pada baris ini tidak diterapkan.',
            $lineNumber,
            $this->formatBobot($totalBaris),
            $komponen->kode ?? $komponen->nama,
            $this->formatBobot($bobotAsesmen),
        );

        return collect($selBaris)
            ->map(function (array $sel) use (&$ringkasan): array {
                if ($sel['status'] !== 'update') {
                    return $sel;
                }

                $ringkasan['sel_update']--;
                $ringkasan['sel_invalid']++;

                return [
                    ...$sel,
                    'status' => 'invalid',
                    'bobot' => null,
                    'keterangan' => 'total bobot baris ini melebihi bobot Asesmen',
                ];
            })
            ->all();
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function parseNumeric(string $value): ?float
    {
        $normalized = str_replace([' ', ','], ['', '.'], trim($value));

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    private function formatBobot(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
