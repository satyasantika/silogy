<?php

use App\Modules\Panduan\Services\PerenderPanduan;
use App\Modules\Panduan\Support\PeranPanduan;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Tangkapan menu samping berukuran potret (320x836). Tanpa batas lebar, CSS
 * `.pd-bingkai img { width: 100% }` meregangkannya selebar kolom artikel
 * sehingga tak proporsional dengan gambar lanskap lain.
 */
it('membatasi gambar potret ke lebar aslinya dan tidak mengubah gambar lanskap', function (string $slug) {
    $html = $this->get(route('panduan.peran', ['peran' => $slug]))->assertSuccessful()->getContent();

    preg_match_all('/<figure class="([^"]*)">\s*<a class="pd-bingkai"([^>]*)>\s*<img[^>]*?width="(\d+)"[^>]*?height="(\d+)"/s', $html, $m, PREG_SET_ORDER);

    expect($m)->not->toBeEmpty();

    foreach ($m as [, $kelas, $atribut, $lebar, $tinggi]) {
        if ((int) $tinggi > (int) $lebar) {
            // Tinggi tampil tak boleh melewati batas, lebar tak boleh melebihi aslinya.
            $diharapkan = min((int) $lebar, (int) round(PerenderPanduan::BATAS_TINGGI_POTRET * $lebar / $tinggi));

            expect($kelas)->toContain('pd-gbr-tegak')
                ->and($atribut)->toContain('max-width:'.$diharapkan.'px')
                ->and($diharapkan * $tinggi / $lebar)->toBeLessThanOrEqual(PerenderPanduan::BATAS_TINGGI_POTRET + 1);
        } else {
            expect($kelas)->not->toContain('pd-gbr-tegak')
                ->and($atribut)->not->toContain('max-width');
        }
    }
})->with(array_keys(PeranPanduan::semua()));

it('gambar menu samping di tiap halaman peran bertanda potret dan terbatas tingginya', function () {
    $dicek = 0;

    foreach (PeranPanduan::semua() as $slug => $definisi) {
        $html = $this->get(route('panduan.peran', ['peran' => $slug]))->getContent();

        if (preg_match_all('/<figure class="pd-gbr pd-gbr-tegak">\s*<a class="pd-bingkai"[^>]*max-width:2\d\dpx[^>]*>\s*<img[^>]*src="[^"]*sidebar-[^"]*"/s', $html) > 0) {
            $dicek++;
        }
    }

    // Tujuh peran memuat tangkapan sidebar-nya sendiri.
    expect($dicek)->toBeGreaterThanOrEqual(6);
});
