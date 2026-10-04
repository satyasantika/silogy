<?php

use App\Models\User;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\PengaturanSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use App\Modules\Simulasi\Support\SesiTab;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/**
 * Instans aplikasi di test dipakai ulang antarrequest, sedangkan di PHP-FPM
 * setiap request memakai proses baru. Keadaan yang dipasang SesiSandboxTab
 * (root URL, nama dan path cookie sesi) harus dibuang di antara dua request
 * supaya tab kedua tidak tertimpa sisa tab pertama.
 */
function bukaTab(string $token, string $path): TestResponse
{
    URL::forceRootUrl(null);
    URL::useAssetOrigin(null);
    config(['session.cookie' => 'laravel_session', 'session.path' => '/']);
    app('session')->forgetDrivers();
    auth()->logout();

    return test()->get('http://localhost/s/'.$token.$path);
}

beforeEach(function () {
    config()->set('simulasi.izinkan_coba_peran', true);
    RateLimiter::clear('panduan-coba-peran');
    Cache::flush();

    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();

    app(SimulasiService::class)->aturCobaPeran(true);
});

/** Memulai tab di contoh terisi bersama untuk (peran, tingkat); mengembalikan token tab dari URL tujuan. */
function mulaiTab(string $peran, string $level = 'prodi'): string
{
    $respons = test()->post(route('panduan.coba', ['peran' => $peran]), ['level' => $level]);

    $respons->assertRedirect();
    preg_match('#/s/([a-z0-9]{24})/simulasi/masuk#', (string) $respons->headers->get('Location'), $cocok);

    expect($cocok)->toHaveCount(2);

    return $cocok[1];
}

it('menyiapkan tab baru dan mengarahkan ke jalur masuk bertoken, tanpa login di halaman panduan', function () {
    $token = mulaiTab('tim-kurikulum');

    expect(auth()->check())->toBeFalse()
        ->and(SesiTab::cari($token))->not->toBeNull();
});

it('masuk lewat tiket memasang peran aktif dan mendarat di dasbor tab itu', function () {
    $token = mulaiTab('tim-kurikulum');

    $respons = bukaTab($token, '/simulasi/masuk');

    $respons->assertRedirect();
    expect($respons->headers->get('Location'))->toContain("/s/$token/dashboard")
        ->and(auth()->user()->username)->toStartWith('sim-timkur-');
});

it('tiket hanya berlaku sekali', function () {
    $token = mulaiTab('dosen-pengampu');

    bukaTab($token, '/simulasi/masuk')->assertRedirect();

    bukaTab($token, '/simulasi/masuk')->assertNotFound();
});

it('menolak token yang tidak dikenal dengan 404 sebelum sesi dimulai', function () {
    $token = str_repeat('a', 24);

    $respons = bukaTab($token, '/simulasi/masuk');

    $respons->assertNotFound();
    expect($respons->headers->getCookies())->toBeEmpty();
});

it('jalur masuk tanpa awalan tab tidak ada', function () {
    $this->get('/simulasi/masuk')->assertNotFound();
});

it('cookie sesi tiap tab dibatasi ke path dan nama tabnya sendiri', function () {
    $tokenA = mulaiTab('dosen-pengampu', 'prodi');
    $tokenB = mulaiTab('pimpinan', 'prodi');

    $cookieA = collect(bukaTab($tokenA, '/simulasi/masuk')->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'silogy_tab_'.$tokenA);
    $cookieB = collect(bukaTab($tokenB, '/simulasi/masuk')->headers->getCookies())
        ->first(fn ($c) => $c->getName() === 'silogy_tab_'.$tokenB);

    expect($cookieA)->not->toBeNull()
        ->and($cookieB)->not->toBeNull()
        ->and($cookieA->getPath())->toEndWith("/s/$tokenA")
        ->and($cookieB->getPath())->toEndWith("/s/$tokenB")
        ->and($cookieA->getName())->not->toBe($cookieB->getName());
});

it('dua tab satu pengunjung memakai sandbox yang sama tapi akun berbeda', function () {
    $tokenA = mulaiTab('dosen-pengampu');
    $tokenB = mulaiTab('pimpinan');

    $a = SesiTab::cari($tokenA);
    $b = SesiTab::cari($tokenB);

    expect($a['jalan'])->toBe($b['jalan'])
        ->and($a['user'])->not->toBe($b['user'])
        ->and(SimulasiJalan::query()->masihAda()->count())->toBe(1);
});

it('semua pengunjung memakai contoh terisi bersama yang sama, tanpa kapasitas tambahan', function () {
    $a = SesiTab::cari(mulaiTab('dosen-pengampu'));
    $b = SesiTab::cari(mulaiTab('dosen-pengampu'));

    expect($a['jalan'])->toBe($b['jalan'])
        ->and($a['ruang'])->toBeFalse()
        ->and(SimulasiJalan::query()->masihAda()->count())->toBe(1);
});

it('enam peran di tingkat prodi mendarat di akun yang benar', function () {
    PengaturanSimulasi::atur('batas_coba', 1000);

    $diharapkan = [
        'admin-unit' => 'sim-adminprodi',
        'tim-kurikulum' => 'sim-timkur',
        'koordinator-mk' => 'sim-korma',
        'dosen-pengampu' => 'sim-dosen',
        'pimpinan' => 'sim-kaprodi',
        'auditor-mutu' => 'sim-auditor',
    ];

    foreach ($diharapkan as $slug => $kunci) {
        $token = mulaiTab($slug);
        bukaTab($token, '/simulasi/masuk')->assertRedirect();

        expect(auth()->user()->username)->toStartWith($kunci.'-');
        auth()->logout();
    }
});

it('menolak tingkat yang tidak dikenal', function () {
    $this->post(route('panduan.coba', ['peran' => 'pimpinan']), ['level' => 'dunia'])->assertNotFound();
});

it('menutup rute bila mode latihan tertutup', function () {
    app(SimulasiService::class)->aturCobaPeran(false);

    $this->post(route('panduan.coba', ['peran' => 'pimpinan']))->assertNotFound();
});

it('tidak bisa dipakai memasuki akun nyata walau namanya mirip', function () {
    $jalan = app(SimulasiService::class)->contohTerisi();

    $penyusup = Ranah::sebagai($jalan->id, fn () => User::factory()->create([
        'username' => 'sim-timkur-'.$jalan->kode().'-palsu',
        'email' => 'palsu@'.AkunSimulasi::DOMAIN,
    ]));
    $asli = User::query()->where('username', 'timkur')->firstOrFail();

    expect(app(SimulasiService::class)->memiliki($jalan, $asli))->toBeFalse()
        ->and(app(SimulasiService::class)->memiliki($jalan, $penyusup))->toBeFalse();
});

it('tidak menyediakan jalur masuk untuk Super Admin', function () {
    $this->post('/panduan/super-admin/coba')->assertNotFound();

    expect(array_keys(AkunSimulasi::akunPanduan()))->not->toContain('super-admin');
});

it('menyisakan tab yang kedaluwarsa sebagai 404', function () {
    $token = mulaiTab('pimpinan');
    SesiTab::lupakan($token);

    bukaTab($token, '/dashboard')->assertNotFound();
});

it('membatasi jumlah percobaan per menit sesuai pengaturan di basis data', function () {
    PengaturanSimulasi::atur('batas_coba', 2); // dipaksa naik ke batas bawah 5

    expect(PengaturanSimulasi::ambil('batas_coba'))->toBe(5);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('panduan.coba', ['peran' => 'pimpinan']));
    }

    $this->post(route('panduan.coba', ['peran' => 'pimpinan']))->assertStatus(429);
});

it('contoh terisi yang belum selesai dibangun memberi pesan sabar, bukan galat dan bukan bangun kembar', function () {
    app(SimulasiService::class)->mulai(mode: 'terisi', bersama: true);

    $this->from(route('panduan.level', ['level' => 'prodi']))
        ->post(route('panduan.coba', ['peran' => 'pimpinan']), ['level' => 'prodi'])
        ->assertSessionHas('panduan_galat', fn ($pesan) => str_contains((string) $pesan, 'sedang disiapkan'));

    expect(SimulasiJalan::query()->bersama()->count())->toBe(1);
});

// ── Keluaran HTML di bawah awalan tab ────────────────────────────────────

/** Masuk ke tab sebagai akun sandbox tertentu, tanpa melalui tombol panduan. */
function halamanTab(string $kunciAkun, string $path = '/dashboard'): array
{
    $jalan = app(SimulasiService::class)->contohTerisi();
    $user = Ranah::sebagai($jalan->id, fn () => User::query()
        ->where('username', AkunSimulasi::username($kunciAkun, $jalan->kode()))->firstOrFail());

    $token = SesiTab::token();
    SesiTab::buat($token, $jalan, (string) $user->getKey(), 'admin-unit');

    URL::forceRootUrl(null);
    URL::useAssetOrigin(null);

    $respons = test()->actingAs($user)->get('http://localhost/s/'.$token.$path);

    return [$token, $respons, (string) $respons->getContent()];
}

it('mengarahkan AJAX Livewire ke path tab supaya membawa cookie tab itu', function () {
    [$token, $respons, $html] = halamanTab('sim-adminprodi');

    $respons->assertSuccessful();

    expect($html)->toContain('data-update-uri="/s/'.$token.'/livewire/update"')
        ->and($html)->not->toContain('data-update-uri="/livewire/update"');
});

it('menjaga aset statis tetap di origin asli, bukan di bawah awalan tab', function () {
    [$token, , $html] = halamanTab('sim-adminprodi');

    preg_match_all('#(?:src|href)="([^"]+\.(?:css|js|woff2)[^"]*)"#', $html, $cocok);

    expect($cocok[1])->not->toBeEmpty();

    foreach ($cocok[1] as $url) {
        expect($url)->not->toContain('/s/'.$token.'/', "aset ber-awalan tab: $url");
    }
});

it('menautkan navigasi dengan awalan tab sehingga klik tetap di sesi yang sama', function () {
    [$token, , $html] = halamanTab('sim-adminprodi');

    expect($html)->toContain('href="http://localhost/s/'.$token.'/mahasiswas"');
});

it('signed URL yang dibuat di dalam tab tetap lolos validasi di dalam tab', function () {
    Route::get('/uji-tanda-tangan', fn () => response('ok'))
        ->middleware(['web', 'signed'])
        ->name('uji.tanda-tangan');
    app('router')->getRoutes()->refreshNameLookups();

    $jalan = app(SimulasiService::class)->contohTerisi();
    $token = SesiTab::token();
    SesiTab::buat($token, $jalan, (string) User::query()->firstOrFail()->getKey(), 'admin-unit');

    URL::forceRootUrl(null);
    $dalamTab = test()->get('http://localhost/s/'.$token.'/dashboard');
    $dalamTab->assertRedirect(); // sekadar membuka tab; root URL kini ber-awalan

    $url = URL::signedRoute('uji.tanda-tangan');

    expect($url)->toContain('/s/'.$token.'/uji-tanda-tangan');

    $this->get($url)->assertOk();
    $this->get(str_replace('/uji-tanda-tangan', '/uji-tanda-tangan-lain', $url))->assertNotFound();
});

it('tidak menyentuh request biasa di luar tab', function () {
    URL::forceRootUrl(null);

    $this->get('http://localhost/panduan')->assertSuccessful();

    expect(config('session.cookie'))->not->toStartWith('silogy_tab_');
});
