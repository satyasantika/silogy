<?php

/*
 * Penjaga regresi kontras mode gelap.
 *
 * Test ini GAGAL bila perubahan produksi berikut masuk lagi:
 *   - menulis `style="background:#fff;..."` (atau white / rgb(255,255,255))
 *     tanpa `color:` eksplisit di atribut yang sama pada view Filament:
 *     teks mewarisi warna tema (terang di mode gelap) dan menjadi putih di
 *     atas putih; atau
 *   - menulis lagi warna teks inline tetap `color:#0b3914`, `#2563eb`,
 *     `#c1121f`, `#b45309`, `#15803d` (kontras < 4.5:1 di atas #18181b).
 *     Inline `style` tak bisa ditimpa aturan `.dark`; pakai kelas
 *     `.silogy-label-langkah` / `.silogy-aksen-*` dari
 *     resources/views/filament/hooks/kontras-tema-styles.blade.php.
 *
 * Pengecualian hanya lewat daftar di bawah, masing-masing dengan alasan.
 */

const TJ_AKAR = __DIR__.'/../../..';

/** @var list<array{path: string, alasan: string}> Latar putih tanpa color: eksplisit yang sengaja dibiarkan. */
const TJ_PENGECUALIAN_LATAR_PUTIH = [
    [
        'path' => 'resources/views/filament/modules/kurikulum/partials/kode-keterangan-trigger.blade.php',
        'alasan' => 'Kartu dialog keterangan kode sengaja putih di kedua mode (pop-up solid); pembungkus hanya berisi '
            .'anak yang masing-masing menulis color: sendiri (#14201a, #4b5563, #6b7280, #fff pada banner gradien).',
    ],
];

/** @var list<array{path: string, alasan: string}> Warna teks inline tetap yang sengaja dibiarkan. */
const TJ_PENGECUALIAN_WARNA_TEKS = [
    // (kosong) banner gradien hijau memakai color:#fff dan #0b3914 hanya pada
    // `background`, sehingga tidak terdeteksi.
];

/**
 * @return list<array{path: string, atribut: string}>
 */
function tj_atribut_style(string $path, string $isi): array
{
    preg_match_all('/\bstyle=(["\'])(.*?)\1/s', $isi, $cocok);

    return array_map(fn (string $atribut): array => ['path' => $path, 'atribut' => $atribut], $cocok[2]);
}

function tj_latar_putih_tanpa_warna(string $atribut): bool
{
    $latarPutih = '/(?<![-\w])background(?:-color)?\s*:\s*(?:#fff(?:fff)?|white|rgb\(\s*255\s*,\s*255\s*,\s*255\s*\))(?![\w#])/i';
    $adaWarna = '/(?<![-\w])color\s*:/i';

    return preg_match($latarPutih, $atribut) === 1 && preg_match($adaWarna, $atribut) !== 1;
}

function tj_warna_teks_tetap(string $atribut): ?string
{
    if (preg_match('/(?<![-\w])color\s*:\s*#(0b3914|2563eb|c1121f|b45309|15803d)(?![0-9a-f])/i', $atribut, $m) === 1) {
        return '#'.strtolower($m[1]);
    }

    return null;
}

/**
 * @return list<array{path: string, atribut: string}>
 */
function tj_semua_atribut(): array
{
    $hasil = [];
    $akar = realpath(TJ_AKAR);

    $sumber = [
        [$akar.'/resources/views/filament', '/\.blade\.php$/'],
        [$akar.'/app', '/\.php$/'],
    ];

    foreach ($sumber as [$dir, $pola]) {
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));

        foreach ($iter as $berkas) {
            if (! $berkas->isFile() || preg_match($pola, $berkas->getFilename()) !== 1) {
                continue;
            }

            $path = ltrim(str_replace($akar, '', $berkas->getPathname()), '/');
            array_push($hasil, ...tj_atribut_style($path, (string) file_get_contents($berkas->getPathname())));
        }
    }

    return $hasil;
}

function tj_diizinkan(string $path, array $daftar): bool
{
    return collect($daftar)->contains(fn (array $e): bool => $e['path'] === $path && $e['alasan'] !== '');
}

it('mendeteksi latar putih inline tanpa warna teks eksplisit', function (): void {
    expect(tj_latar_putih_tanpa_warna('padding:4px;background:#fff;'))->toBeTrue()
        ->and(tj_latar_putih_tanpa_warna('background-color: #FFFFFF'))->toBeTrue()
        ->and(tj_latar_putih_tanpa_warna('background:white;border:1px solid #ccc'))->toBeTrue()
        ->and(tj_latar_putih_tanpa_warna('background:rgb(255, 255, 255)'))->toBeTrue()
        ->and(tj_latar_putih_tanpa_warna('background:#fff;color:#111'))->toBeFalse()
        ->and(tj_latar_putih_tanpa_warna('background:#ffffff; color : #14201a'))->toBeFalse()
        ->and(tj_latar_putih_tanpa_warna('background:#fffbeb'))->toBeFalse()
        ->and(tj_latar_putih_tanpa_warna('background:rgba(255,255,255,.1)'))->toBeFalse()
        ->and(tj_latar_putih_tanpa_warna('border-color:#fff'))->toBeFalse();
});

it('mendeteksi warna teks inline tetap yang tak punya pasangan gelap', function (): void {
    expect(tj_warna_teks_tetap('font-weight:700;color:#0b3914;'))->toBe('#0b3914')
        ->and(tj_warna_teks_tetap('color: #2563EB'))->toBe('#2563eb')
        ->and(tj_warna_teks_tetap('background:linear-gradient(120deg,#007000,#0b3914);color:#fff'))->toBeNull()
        ->and(tj_warna_teks_tetap('border-left:5px solid #2563eb;background:rgba(37,99,235,.07)'))->toBeNull()
        ->and(tj_warna_teks_tetap('accent-color:#2563eb'))->toBeNull()
        ->and(tj_warna_teks_tetap('color:#2563eb99'))->toBeNull();
});

it('tidak ada view Filament dengan latar putih inline tanpa color: eksplisit', function (): void {
    $pelanggar = collect(tj_semua_atribut())
        ->filter(fn (array $a): bool => tj_latar_putih_tanpa_warna($a['atribut']))
        ->reject(fn (array $a): bool => tj_diizinkan($a['path'], TJ_PENGECUALIAN_LATAR_PUTIH))
        ->map(fn (array $a): string => $a['path'].' => '.$a['atribut'])
        ->values()
        ->all();

    expect($pelanggar)->toBe([], "Latar putih inline tanpa color: eksplisit (putih di atas putih pada mode gelap):\n".implode("\n", $pelanggar));
});

it('tidak ada warna teks inline tetap yang kontrasnya gagal di mode gelap', function (): void {
    $pelanggar = collect(tj_semua_atribut())
        ->filter(fn (array $a): bool => tj_warna_teks_tetap($a['atribut']) !== null)
        ->reject(fn (array $a): bool => tj_diizinkan($a['path'], TJ_PENGECUALIAN_WARNA_TEKS))
        ->map(fn (array $a): string => $a['path'].' => '.tj_warna_teks_tetap($a['atribut']).' dalam '.$a['atribut'])
        ->values()
        ->all();

    expect($pelanggar)->toBe([], "Warna teks inline tetap; pakai kelas silogy-label-langkah / silogy-aksen-* (pasangan .dark):\n".implode("\n", $pelanggar));
});

it('menjaga setiap pengecualian tetap beralasan, menunjuk berkas yang ada, dan masih dipakai', function (): void {
    $latar = collect(tj_semua_atribut())->filter(fn (array $a): bool => tj_latar_putih_tanpa_warna($a['atribut']))->pluck('path');
    $teks = collect(tj_semua_atribut())->filter(fn (array $a): bool => tj_warna_teks_tetap($a['atribut']) !== null)->pluck('path');

    foreach (TJ_PENGECUALIAN_LATAR_PUTIH as $entri) {
        expect($entri['alasan'])->not->toBe('')
            ->and(file_exists(TJ_AKAR.'/'.$entri['path']))->toBeTrue()
            ->and($latar->contains($entri['path']))->toBeTrue("Pengecualian latar putih usang: {$entri['path']}");
    }

    foreach (TJ_PENGECUALIAN_WARNA_TEKS as $entri) {
        expect($entri['alasan'])->not->toBe('')
            ->and(file_exists(TJ_AKAR.'/'.$entri['path']))->toBeTrue()
            ->and($teks->contains($entri['path']))->toBeTrue("Pengecualian warna teks usang: {$entri['path']}");
    }

    expect(TJ_PENGECUALIAN_LATAR_PUTIH)->not->toBeEmpty();
});
