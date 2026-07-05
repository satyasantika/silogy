<?php

use App\Models\User;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('menampilkan halaman 404 kustom dengan tombol navigasi', function () {
    $this->get('/rute-tidak-ada-silogy')
        ->assertNotFound()
        ->assertSee('404')
        ->assertSee('Not Found')
        ->assertSee('Halaman tidak ditemukan')
        ->assertSee('bi-arrow-left')
        ->assertSee('Kembali')
        ->assertSee('bi-speedometer2')
        ->assertSee('Dashboard')
        ->assertSee('bi-door-open')
        ->assertSee('Masuk');
});

it('menampilkan halaman 403 kustom dengan tombol logout bila sudah login', function () {
    Route::get('/uji-forbidden-silogy', fn () => abort(403));

    $dosen = User::query()->where('username', 'dosen')->firstOrFail();

    $this->actingAs($dosen)
        ->get('/uji-forbidden-silogy')
        ->assertForbidden()
        ->assertSee('403')
        ->assertSee('Forbidden')
        ->assertSee('Akses ditolak')
        ->assertSee('bi-shield-lock-fill')
        ->assertSee('bi-box-arrow-right')
        ->assertSee('Logout');
});
