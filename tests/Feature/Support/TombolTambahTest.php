<?php

use App\Support\Filament\TombolTambah;
use Filament\Support\Icons\Heroicon;

/*
 * Tombol Tambah tampil sebagai tombol bulat berikon plus di samping Impor
 * massal, setinggi tombol itu (ukuran tombol sama, hanya labelnya
 * disembunyikan dan bentuknya dibulatkan lewat CSS tema).
 */

it('tombol tambah berupa ikon plus tanpa label tampak, tetap berlabel bagi pembaca layar', function () {
    $tombol = TombolTambah::make();

    expect($tombol->getName())->toBe('create')
        ->and($tombol->getLabel())->toBe('Tambah')
        ->and($tombol->isLabelHidden())->toBeTrue()
        ->and($tombol->getIcon())->toBe(Heroicon::Plus)
        ->and($tombol->getTooltip())->toBe('Tambah satu per satu')
        ->and($tombol->getExtraAttributes()['class'] ?? '')->toContain('silogy-tombol-bulat');
});

it('tema panel membulatkan tombol bulat dan membuat lebarnya sama dengan tinggi', function () {
    $css = file_get_contents(dirname(__DIR__, 3).'/resources/views/filament/hooks/unsil-theme-styles.blade.php');

    // Padding horizontal = padding vertikal tombol md (spacing*2) sehingga
    // lebar = tinggi = 36px. aspect-ratio tidak dipakai: di dalam .fi-ac
    // (flex) ia menyusutkan lebar ke ikon dan menghasilkan kapsul 20x36.
    expect($css)->toContain('.fi-btn.silogy-tombol-bulat')
        ->and($css)->toContain('border-radius: 9999px')
        ->and($css)->toContain('padding-inline: calc(var(--spacing) * 2)')
        ->and($css)->not->toContain('aspect-ratio');
});
