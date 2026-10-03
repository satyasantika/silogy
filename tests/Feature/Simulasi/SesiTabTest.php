<?php

use App\Models\User;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use App\Modules\Simulasi\Support\SesiTab;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();

    app(SimulasiService::class)->aturCobaPeran(true);
});

/** Memulai tab untuk (peran, tingkat); mengembalikan token dari URL tujuan. */
function mulaiTab(string $peran, string $level = 'prodi', string $pengenal = 'pengunjung-uji-'.'x'): string
{
    $respons = test()
        ->withCookie('silogy_pengunjung', str_pad($pengenal, 40, 'x'))
        ->post(route('panduan.coba', ['peran' => $peran]), ['level' => $level]);

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
    $token = mulaiTab('tim-kurikulum', 'fak');

    $respons = bukaTab($token, '/simulasi/masuk');

    $respons->assertRedirect();
    expect($respons->headers->get('Location'))->toContain("/s/$token/dashboard")
        ->and(auth()->user()->username)->toStartWith('sim-timkurfak-');
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

it('pengunjung berbeda mendapat sandbox berbeda', function () {
    $a = SesiTab::cari(mulaiTab('dosen-pengampu', 'prodi', 'orang-a'));
    $b = SesiTab::cari(mulaiTab('dosen-pengampu', 'prodi', 'orang-b'));

    expect($a['jalan'])->not->toBe($b['jalan']);
});

it('enam peran × tiga tingkat semuanya bisa dimasuki dan mendarat di akun yang benar', function () {
    config()->set('simulasi.batas_coba_per_menit', 1000);

    $diharapkan = [
        'admin-unit' => ['univ' => 'sim-adminuniv', 'fak' => 'sim-adminfak', 'prodi' => 'sim-adminprodi'],
        'tim-kurikulum' => ['univ' => 'sim-timkuruniv', 'fak' => 'sim-timkurfak', 'prodi' => 'sim-timkur'],
        'koordinator-mk' => ['univ' => 'sim-kormauniv', 'fak' => 'sim-kormafak', 'prodi' => 'sim-korma'],
        'dosen-pengampu' => ['univ' => 'sim-dosenuniv', 'fak' => 'sim-dosenfak', 'prodi' => 'sim-dosen'],
        'pimpinan' => ['univ' => 'sim-rektor', 'fak' => 'sim-dekan', 'prodi' => 'sim-kaprodi'],
        'auditor-mutu' => ['univ' => 'sim-auditoruniv', 'fak' => 'sim-auditorfak', 'prodi' => 'sim-auditor'],
    ];

    foreach ($diharapkan as $slug => $perLevel) {
        foreach ($perLevel as $level => $kunci) {
            $token = mulaiTab($slug, $level);
            bukaTab($token, '/simulasi/masuk')->assertRedirect();

            expect(auth()->user()->username)->toStartWith($kunci.'-');
            auth()->logout();
        }
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
    $jalan = app(SimulasiService::class)->klaim(SimulasiService::hashPengunjung('x'));

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

it('membatasi jumlah percobaan per menit', function () {
    config()->set('simulasi.batas_coba_per_menit', 3);

    for ($i = 0; $i < 3; $i++) {
        $this->post(route('panduan.coba', ['peran' => 'pimpinan']));
    }

    $this->post(route('panduan.coba', ['peran' => 'pimpinan']))->assertStatus(429);
});

it('menampilkan pesan, bukan galat, saat ruang latihan penuh', function () {
    config()->set('simulasi.maks_sandbox', 1);
    app(SimulasiService::class)->klaim(SimulasiService::hashPengunjung('pertama'));

    $this->from(route('panduan.level', ['level' => 'prodi']))
        ->withCookie('silogy_pengunjung', str_pad('kedua', 40, 'x'))
        ->post(route('panduan.coba', ['peran' => 'pimpinan']), ['level' => 'prodi'])
        ->assertSessionHas('panduan_galat');
});

// ── Keluaran HTML di bawah awalan tab ────────────────────────────────────

/** Masuk ke tab sebagai akun sandbox tertentu, tanpa melalui tombol panduan. */
function halamanTab(string $kunciAkun, string $path = '/dashboard'): array
{
    $jalan = app(SimulasiService::class)->klaim(SimulasiService::hashPengunjung('uji-html'));
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

    $jalan = app(SimulasiService::class)->klaim(SimulasiService::hashPengunjung('uji-ttd'));
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
