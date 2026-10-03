<?php

use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Simulasi\Support\AkunSimulasi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

it('membuka indeks panduan untuk tamu dan menawarkan tiga tingkat', function () {
    $respons = $this->get(route('panduan.indeks'))
        ->assertSuccessful()
        ->assertSee('Mulai dari tingkat unit Anda');

    foreach (AkunSimulasi::LEVEL as $kunci => $label) {
        $respons->assertSee($label)->assertSee(route('panduan.level', ['level' => $kunci]), escape: false);
    }
});

it('menampilkan peran yang bisa dicoba di tiap halaman tingkat', function (string $level) {
    $respons = $this->get(route('panduan.level', ['level' => $level]))->assertSuccessful();

    foreach (PeranPanduan::bisaDicoba() as $slug => $definisi) {
        $respons->assertSee($definisi['label'], escape: false)
            ->assertSee(route('panduan.peran', ['peran' => $slug, 'level' => $level]), escape: false);
    }
})->with(array_keys(AkunSimulasi::LEVEL));

it('tidak menampilkan tombol coba selama mode latihan tertutup', function () {
    $this->get(route('panduan.level', ['level' => 'prodi']))
        ->assertSuccessful()
        ->assertDontSee('Coba di tab baru');
});

it('menolak tingkat yang tidak dikenal', function () {
    $this->get('/panduan/level/dunia')->assertNotFound();
});

it('merender halaman tiap peran untuk tamu', function (string $slug) {
    $this->get(route('panduan.peran', ['peran' => $slug]))
        ->assertSuccessful()
        ->assertSee(PeranPanduan::definisi($slug)['label'], escape: false);
})->with(PeranPanduan::slug());

it('mengabaikan parameter tingkat yang ngawur pada halaman peran', function () {
    $this->get(route('panduan.peran', ['peran' => 'pimpinan', 'level' => 'ngawur']))->assertSuccessful();
});

it('menolak slug peran yang tidak dikenal', function () {
    $this->get('/panduan/bukan-peran')->assertNotFound();
});

/**
 * Super Admin sengaja disembunyikan dari panduan publik: siapa pun yang
 * butuh peran ini wajib login sungguhan, tidak lewat halaman panduan anonim.
 */
it('menyembunyikan panduan Super Admin dari publik', function () {
    $this->get('/panduan/super-admin')->assertNotFound();

    foreach (AkunSimulasi::LEVEL as $kunci => $label) {
        $this->get(route('panduan.level', ['level' => $kunci]))
            ->assertDontSee('Struktur kampus, akun, semester, dan jejak sistem.', escape: false);
    }
});

/**
 * Rute Filament terdaftar LEBIH DULU daripada routes/web.php, jadi sebuah
 * resource atau page bersiput 'panduan' di masa depan akan membajak halaman
 * ini tanpa suara. Tes ini yang akan berteriak lebih dulu.
 */
it('tidak dibajak rute panel Filament', function () {
    foreach (['/panduan' => 'panduan.indeks', '/panduan/level/prodi' => 'panduan.level'] as $uri => $nama) {
        $cocok = Route::getRoutes()->match(Request::create($uri, 'GET'));

        expect($cocok->getName())->toBe($nama);
    }
});

it('menyajikan aset gambar manual', function () {
    $berkas = base_path('docs/user-manual/aset/login.png');

    if (! is_file($berkas)) {
        $this->markTestSkipped('Tangkapan layar belum dibuat.');
    }

    $this->get(route('panduan.aset', ['berkas' => 'login.png']))
        ->assertSuccessful()
        ->assertHeader('content-type', 'image/png');
});

it('menolak penelusuran direktori lewat rute aset', function () {
    $this->get('/panduan/aset/..%2F..%2F.env')->assertNotFound();
    $this->get('/panduan/aset/berkas.txt')->assertNotFound();
});

it('menaut panduan dari beranda dan tidak menyisakan tautan mati di footer', function () {
    $respons = $this->get('/')->assertSuccessful();
    $html = $respons->getContent();

    expect($html)->toContain(route('panduan.indeks'));

    // Empat tautan "Fitur" di footer dulu semuanya href="#".
    preg_match('#<nav class="fl">(.*?)</nav>#s', $html, $cocok);

    expect($cocok[1] ?? '')->not->toContain('href="#"');
});
