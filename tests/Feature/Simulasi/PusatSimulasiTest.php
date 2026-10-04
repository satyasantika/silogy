<?php

use App\Models\User;
use App\Modules\Simulasi\Filament\Pages\PusatSimulasi;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Models\SimulasiPeranTerisi;
use App\Modules\Simulasi\Services\RuangSimulasi;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\PengaturanSimulasi;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();
});

function masukSebagai(string $username): User
{
    $user = User::query()->where('username', $username)->firstOrFail();
    test()->actingAs($user);

    return $user;
}

it('hanya bisa diakses Super Admin', function () {
    masukSebagai('superadmin');

    expect(PusatSimulasi::canAccess())->toBeTrue();
});

it('menolak seluruh peran selain Super Admin', function (string $username) {
    masukSebagai($username);

    expect(PusatSimulasi::canAccess())->toBeFalse();
})->with([
    'adminprodi',
    'timkur',
    'korma',
    'dosen',
    'kaprodi',
    'auditor',
]);

it('menolak tamu yang belum masuk', function () {
    expect(PusatSimulasi::canAccess())->toBeFalse();
});

it('ikut mati bila simulasi dimatikan lewat konfigurasi', function () {
    masukSebagai('superadmin');
    config()->set('simulasi.aktif', false);

    expect(PusatSimulasi::canAccess())->toBeFalse();
});

it('tidak muncul di navigasi bila tidak bisa diakses', function () {
    masukSebagai('timkur');

    expect(PusatSimulasi::shouldRegisterNavigation())->toBeFalse();
});

it('memuat halaman untuk Super Admin, kosong maupun berisi sandbox', function () {
    masukSebagai('superadmin');

    $this->get('/simulasi')->assertSuccessful()->assertSee('Belum ada ruang');

    $jalan = app(SimulasiService::class)->buat()->jalan;

    $this->get('/simulasi')->assertSuccessful()->assertSee($jalan->pin);
});

it('menolak permintaan HTTP dari peran lain', function () {
    masukSebagai('timkur');

    $this->get('/simulasi')->assertForbidden();
});

it('Super Admin inti tidak melihat akun sandbox di daftar pengguna', function () {
    masukSebagai('superadmin');
    app(SimulasiService::class)->buat();

    expect(User::query()->where('username', 'like', 'sim-%')->count())->toBe(0);
});

it('hapusSatu membongkar satu sandbox dan hanya Super Admin yang boleh', function () {
    $simulasi = app(SimulasiService::class);
    $a = $simulasi->buat()->jalan;
    $b = $simulasi->buat()->jalan;

    masukSebagai('timkur');
    expect(fn () => (new PusatSimulasi)->hapusSatu($a->id))->toThrow(HttpException::class);

    masukSebagai('superadmin');
    (new PusatSimulasi)->hapusSatu($a->id);

    expect($a->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and($b->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI);
});

it('tombol sakelar mode latihan berganti label dan tetap membalik status pada klik berulang di komponen yang sama', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    masukSebagai('superadmin');
    $simulasi = app(SimulasiService::class);
    $simulasi->aturCobaPeran(false);

    $komponen = Livewire::test(PusatSimulasi::class)
        ->assertActionExists('alihkanCobaPeran', fn ($aksi) => $aksi->getLabel() === 'Buka mode latihan')
        ->assertSee('Tertutup');

    // Klik 1: tertutup -> terbuka. Label dan kartu status ikut berubah tanpa memuat ulang halaman.
    $komponen->callAction('alihkanCobaPeran')
        ->assertActionExists('alihkanCobaPeran', fn ($aksi) => $aksi->getLabel() === 'Tutup mode latihan')
        ->assertSee('Terbuka');
    expect($simulasi->cobaPeranTerbuka())->toBeTrue();

    // Klik 2 di komponen yang SAMA: harus menutup lagi, bukan menulis 'terbuka' untuk kedua kalinya.
    $komponen->callAction('alihkanCobaPeran')
        ->assertActionExists('alihkanCobaPeran', fn ($aksi) => $aksi->getLabel() === 'Buka mode latihan')
        ->assertSee('Tertutup');
    expect($simulasi->cobaPeranTerbuka())->toBeFalse();

    // Klik 3: terbuka lagi.
    $komponen->callAction('alihkanCobaPeran');
    expect($simulasi->cobaPeranTerbuka())->toBeTrue();
});

it('sakelar membalik keadaan TERKINI walau admin lain sudah mengubahnya di sela-sela', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    masukSebagai('superadmin');
    $simulasi = app(SimulasiService::class);
    $simulasi->aturCobaPeran(false);

    $komponen = Livewire::test(PusatSimulasi::class);

    // Admin lain membukanya setelah halaman ini dimuat (tombol di layar ini masih berlabel "Buka").
    $simulasi->aturCobaPeran(true);

    // Klik pada tombol yang usang: hasilnya menutup, bukan menimpa dengan "buka" lagi.
    $komponen->callAction('alihkanCobaPeran');

    expect($simulasi->cobaPeranTerbuka())->toBeFalse();
});

it('Siapkan Ruang membangun sebanyak jumlah yang diisi, masing-masing bertoken, dan tidak menyentuh contoh terisi', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    masukSebagai('superadmin');
    config()->set('simulasi.latar_belakang', false);
    $contoh = app(SimulasiService::class)->contohTerisi();

    Livewire::test(PusatSimulasi::class)->callAction('siapkan', ['jumlah' => 2]);

    $ruang = SimulasiJalan::query()->where('bersama', false)->masihAda()->get();

    expect($ruang)->toHaveCount(2)
        ->and($ruang->pluck('pin')->unique()->count())->toBe(2)
        ->and($ruang->every(fn ($r) => $r->mode === 'kosong' && $r->status === SimulasiJalan::STATUS_SELESAI))->toBeTrue()
        ->and($contoh->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI);
});

it('Siapkan Ruang dibatasi sisa kapasitas dan bawaan jumlahnya 1', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    masukSebagai('superadmin');
    config()->set('simulasi.latar_belakang', false);
    PengaturanSimulasi::atur('kapasitas', 2);

    // Bawaan isian jumlah adalah 1.
    Livewire::test(PusatSimulasi::class)->mountAction('siapkan')->assertSchemaStateSet(['jumlah' => 1]);

    // Meminta 5 dengan kapasitas 2: hanya 2 yang dibangun.
    Livewire::test(PusatSimulasi::class)->callAction('siapkan', ['jumlah' => 5]);

    expect(SimulasiJalan::query()->where('bersama', false)->masihAda()->count())->toBe(2);

    // Kapasitas sudah penuh: tidak ada yang bertambah.
    Livewire::test(PusatSimulasi::class)->callAction('siapkan', ['jumlah' => 1]);

    expect(SimulasiJalan::query()->where('bersama', false)->masihAda()->count())->toBe(2);
});

it('Pengaturan menyimpan nilai di basis data, memotongnya ke rentang, dan tidak memerlukan .env', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    masukSebagai('superadmin');

    Livewire::test(PusatSimulasi::class)
        ->callAction('pengaturan', ['kapasitas' => 350, 'batas_coba' => 120, 'batas_token_salah' => 40, 'umur_hari' => 7, 'sewa_menit' => 30])
        ->assertHasNoActionErrors();

    expect(PengaturanSimulasi::semua())->toBe([
        'kapasitas' => 350, 'batas_coba' => 120, 'batas_token_salah' => 40, 'umur_hari' => 7, 'sewa_menit' => 30,
    ]);
});

it('Pengaturan hanya bisa diubah Super Admin', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    masukSebagai('timkur');

    Livewire::test(PusatSimulasi::class)->assertForbidden();

    expect(PengaturanSimulasi::ambil('kapasitas'))->toBe(200);
});

it('daftar ruang menampilkan token dan peran yang sedang dipegang', function () {
    masukSebagai('superadmin');
    $ruang = app(SimulasiService::class)->buat(mode: 'kosong')->jalan;
    app(RuangSimulasi::class)->ambilPeran($ruang, 'dosen-pengampu', 'tab-uji');

    $this->get('/simulasi')->assertSuccessful()
        ->assertSee($ruang->pin)
        ->assertSee('1 / 6')
        ->assertSee('Dosen Pengampu');
});

it('Lepas peran, Ganti token, dan Hapus hanya bisa dilakukan Super Admin', function () {
    $ruang = app(SimulasiService::class)->buat(mode: 'kosong')->jalan;
    app(RuangSimulasi::class)->ambilPeran($ruang, 'pimpinan', 'tab-uji');
    $pinLama = $ruang->pin;

    masukSebagai('timkur');
    expect(fn () => (new PusatSimulasi)->lepasPeran($ruang->id, 'pimpinan'))->toThrow(HttpException::class)
        ->and(fn () => (new PusatSimulasi)->gantiToken($ruang->id))->toThrow(HttpException::class);

    masukSebagai('superadmin');
    (new PusatSimulasi)->lepasPeran($ruang->id, 'pimpinan');
    (new PusatSimulasi)->gantiToken($ruang->id);

    expect(SimulasiPeranTerisi::query()->count())->toBe(0)
        ->and($ruang->fresh()->pin)->not->toBe($pinLama);
});

it('Hapus Semua Ruang membongkar semua ruang, melepas semua peran, dan melewati contoh terisi', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    masukSebagai('superadmin');
    $service = app(SimulasiService::class);
    $contoh = $service->contohTerisi();
    $a = $service->buat(mode: 'kosong')->jalan;
    $b = $service->buat(mode: 'kosong')->jalan;
    app(RuangSimulasi::class)->ambilPeran($a, 'dosen-pengampu', 'tab-a');
    app(RuangSimulasi::class)->ambilPeran($b, 'pimpinan', 'tab-b');

    Livewire::test(PusatSimulasi::class)
        ->callAction('hapusSemua', ['konfirmasi' => 'HAPUS SIMULASI'])
        ->assertHasNoActionErrors();

    expect($a->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and($b->fresh()->status)->toBe(SimulasiJalan::STATUS_DIBONGKAR)
        ->and(SimulasiPeranTerisi::query()->count())->toBe(0)
        ->and($contoh->fresh()->status)->toBe(SimulasiJalan::STATUS_SELESAI);
});
