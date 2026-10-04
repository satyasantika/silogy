<?php

use App\Models\User;
use App\Modules\Simulasi\Filament\Pages\PusatSimulasi;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
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

    $this->get('/simulasi')->assertSuccessful()->assertSee('Belum ada sandbox');

    $jalan = app(SimulasiService::class)->buat()->jalan;

    $this->get('/simulasi')->assertSuccessful()->assertSee($jalan->kode());
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
