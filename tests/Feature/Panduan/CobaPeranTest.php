<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use App\Modules\Simulasi\Services\SimulasiService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('simulasi.izinkan_coba_peran', true);
    RateLimiter::clear('panduan-coba-peran');

    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();
});

/** Bangun simulasi DAN buka mode latihan — sakelarnya kini di basis data. */
function bangunSimulasi(bool $bukaModeLatihan = true): void
{
    $simulasi = app(SimulasiService::class);
    $simulasi->buat();

    if ($bukaModeLatihan) {
        $simulasi->aturCobaPeran(true);
    }
}

it('menolak ketika instans mengunci mode latihan lewat konfigurasi', function () {
    bangunSimulasi();
    config()->set('simulasi.izinkan_coba_peran', false);

    $this->post(route('panduan.coba', ['peran' => 'tim-kurikulum']))->assertNotFound();
});

it('menolak selama Super Admin belum membuka mode latihan', function () {
    bangunSimulasi(bukaModeLatihan: false);

    $this->post(route('panduan.coba', ['peran' => 'tim-kurikulum']))->assertNotFound();

    expect(auth()->check())->toBeFalse();
});

it('terbuka setelah Super Admin menyalakannya, tanpa menyentuh .env', function () {
    bangunSimulasi(bukaModeLatihan: false);

    $simulasi = app(SimulasiService::class);
    expect($simulasi->cobaPeranTerbuka())->toBeFalse();

    $simulasi->aturCobaPeran(true);

    expect($simulasi->cobaPeranTerbuka())->toBeTrue();
    $this->post(route('panduan.coba', ['peran' => 'tim-kurikulum']))->assertRedirect('/dashboard');
});

it('bisa ditutup kembali kapan saja', function () {
    bangunSimulasi();

    app(SimulasiService::class)->aturCobaPeran(false);

    $this->post(route('panduan.coba', ['peran' => 'tim-kurikulum']))->assertNotFound();
});

it('pemutus keras di konfigurasi mengalahkan sakelar Super Admin', function () {
    bangunSimulasi();
    config()->set('simulasi.izinkan_coba_peran', false);

    // Sakelar basis data menyala, tapi instans melarang — hasilnya tetap tertutup.
    expect(app(SimulasiService::class)->aktif()->coba_peran)->toBeTrue()
        ->and(app(SimulasiService::class)->cobaPeranTerbuka())->toBeFalse();
});

it('menutup sendiri ketika data simulasi dihapus', function () {
    bangunSimulasi();
    $simulasi = app(SimulasiService::class);

    expect($simulasi->cobaPeranTerbuka())->toBeTrue();

    $simulasi->hapus();

    expect($simulasi->cobaPeranTerbuka())->toBeFalse();
});

it('membawa sakelar yang menyala saat dibangun ulang', function () {
    bangunSimulasi();

    app(SimulasiService::class)->bangunUlang();

    expect(app(SimulasiService::class)->cobaPeranTerbuka())->toBeTrue();
});

it('tidak menyalakan sakelar sendiri pada simulasi yang baru dibuat', function () {
    app(SimulasiService::class)->buat();

    expect(app(SimulasiService::class)->cobaPeranTerbuka())->toBeFalse();
});

it('menolak dengan pesan ketika simulasi belum dibuat', function () {
    $this->from(route('panduan.peran', ['peran' => 'tim-kurikulum']))
        ->post(route('panduan.coba', ['peran' => 'tim-kurikulum']))
        ->assertRedirect(route('panduan.peran', ['peran' => 'tim-kurikulum']))
        ->assertSessionHas('panduan_galat');

    expect(auth()->check())->toBeFalse();
});

it('memasukkan pengunjung ke akun simulasi dengan peran dan unit siap pakai', function () {
    bangunSimulasi();

    $this->post(route('panduan.coba', ['peran' => 'tim-kurikulum']))
        ->assertRedirect('/dashboard');

    expect(auth()->check())->toBeTrue()
        ->and(auth()->user()->username)->toBe('sim-timkur')
        ->and(session(ActiveRole::SESSION_KEY))->toBe('Tim Kurikulum')
        ->and(session(AcademicUnitTerpilih::SESSION_KEY))->not->toBeNull()
        ->and(session('silogy_sesi_simulasi'))->not->toBeNull();
});

it('bekerja untuk setiap peran yang punya akun latihan', function (string $slug, string $username) {
    bangunSimulasi();

    $this->post(route('panduan.coba', ['peran' => $slug]))->assertRedirect('/dashboard');

    expect(auth()->user()->username)->toBe($username);
})->with([
    ['admin-unit', 'sim-adminprodi'],
    ['tim-kurikulum', 'sim-timkur'],
    ['koordinator-mk', 'sim-korma'],
    ['dosen-pengampu', 'sim-dosen'],
    ['pimpinan', 'sim-kaprodi'],
    ['auditor-mutu', 'sim-auditor'],
]);

/**
 * Inti jaminannya: jalur ini secara struktural tidak bisa memasuki akun nyata,
 * karena akun nyata tidak punya artefak di buku besar simulasi.
 */
it('tidak bisa dipakai memasuki akun nyata walau namanya mirip', function () {
    bangunSimulasi();

    // Akun nyata yang sengaja dibuat menyerupai akun simulasi — surel pun
    // berada di domain simulasi, sehingga hanya buku besar yang membedakannya.
    $penyusup = User::factory()->create([
        'username' => 'sim-timkur-palsu',
        'email' => 'sim-timkur-palsu@simulasi.silogy.test',
    ]);
    $penyusup->syncRoles(['Tim Kurikulum']);

    $asli = User::query()->where('username', 'timkur')->firstOrFail();

    $jalan = app(SimulasiService::class)->aktif();

    expect(app(SimulasiService::class)->memiliki($jalan, $asli))->toBeFalse()
        ->and(app(SimulasiService::class)->memiliki($jalan, $penyusup))->toBeFalse();
});

it('menolak menukar sesi pengunjung yang sedang masuk sebagai akun nyata', function () {
    bangunSimulasi();

    $asli = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($asli)
        ->from(route('panduan.peran', ['peran' => 'tim-kurikulum']))
        ->post(route('panduan.coba', ['peran' => 'tim-kurikulum']))
        ->assertSessionHas('panduan_galat');

    expect(auth()->user()->username)->toBe('timkur');
});

it('hanya menerima POST, bukan GET', function () {
    bangunSimulasi();

    // 405, bukan 404: rutenya ada, metodenya yang ditolak. Justru inilah yang
    // menutup celah login-CSRF lewat <img src="...">.
    $this->get('/panduan/tim-kurikulum/coba')->assertMethodNotAllowed();
});

it('menolak slug peran tanpa akun latihan', function () {
    bangunSimulasi();

    $this->post(route('panduan.coba', ['peran' => 'alur-end-to-end']))->assertNotFound();
});

/**
 * Peran 'super-admin' sengaja disembunyikan dari peta publik â lihat catatan
 * di PeranPanduan::semua(). Rutenya memakai whereIn(PeranPanduan::slug()),
 * jadi slug yang tak terdaftar di sana gagal tertutup (404) di sisi routing,
 * sebelum sempat menyentuh pagar lain di CobaPeranController.
 */
it('menolak mencoba sebagai Super Admin karena slugnya tidak lagi terdaftar', function () {
    bangunSimulasi();

    $this->post('/panduan/super-admin/coba')->assertNotFound();
});

it('membatasi jumlah percobaan per menit', function () {
    bangunSimulasi();
    config()->set('simulasi.batas_coba_per_menit', 3);

    for ($i = 0; $i < 3; $i++) {
        $this->post(route('panduan.coba', ['peran' => 'tim-kurikulum']));
        auth()->logout();
    }

    $this->post(route('panduan.coba', ['peran' => 'tim-kurikulum']))->assertStatus(429);
});
