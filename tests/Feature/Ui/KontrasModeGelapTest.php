<?php

use Illuminate\Support\Facades\File;

/*
 * Mode gelap: teks .fi-main diwarisi putih, jadi latar/teks kustom wajib
 * punya varian gelap. Palet Filament (--gray-50 dst.) TIDAK berganti mode.
 */

it('theme mendefinisikan latar kartu panel yang berganti saat mode gelap', function () {
    $styles = view('filament.hooks.unsil-theme-styles')->render();

    expect($styles)
        ->toContain('--silogy-panel-bg: var(--gray-50, #f9fafb)')
        ->toContain('--silogy-panel-bg: var(--gray-900, #18181b)');
});

it('tidak ada view atau kelas PHP yang memakai palet gray-50 sebagai latar inline', function () {
    $pelanggar = collect(File::allFiles(resource_path('views')))
        ->merge(File::allFiles(app_path()))
        ->filter(fn ($berkas) => str_contains($berkas->getContents(), 'background:var(--gray-50'))
        ->map(fn ($berkas) => $berkas->getRelativePathname())
        ->values()
        ->all();

    expect($pelanggar)->toBe([]);
});

it('kelas teks bernada punya varian mode gelap', function () {
    $styles = view('filament.hooks.status-badge-styles')->render();

    foreach (['success', 'warning', 'danger', 'info', 'kuat', 'redup'] as $nada) {
        expect($styles)
            ->toContain(".silogy-teks-{$nada} {")
            ->toContain(".dark .silogy-teks-{$nada} {");
    }
});

it('token teks panel kiri halaman masuk ikut berganti di mode gelap', function () {
    $isi = File::get(resource_path('views/filament/pages/auth/login.blade.php'));

    // Tiga blok gelap/terang paksa + auto-gelap masing-masing mendefinisikan --tw.
    expect(substr_count($isi, '--tw: #f0f6ff'))->toBe(2)
        ->and(substr_count($isi, '--tw: #212529'))->toBe(2);
});

it('aturan warna strong beranda publik tidak memakai selector @media yang tidak valid', function () {
    $isi = File::get(resource_path('views/layouts/partials/gaya-publik.blade.php'));

    expect($isi)->not->toContain('@media (prefers-color-scheme: dark) { .ci strong }')
        ->and($isi)->toContain('.ci strong { color: var(--c-accent-strong); }');
});
