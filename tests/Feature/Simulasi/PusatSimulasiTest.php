<?php

use App\Models\User;
use App\Modules\Simulasi\Filament\Pages\PusatSimulasi;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('memuat halaman untuk Super Admin', function () {
    masukSebagai('superadmin');

    $this->get('/simulasi')->assertSuccessful();
});

it('menolak permintaan HTTP dari peran lain', function () {
    masukSebagai('timkur');

    $this->get('/simulasi')->assertForbidden();
});
