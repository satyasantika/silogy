<?php

use App\Models\User;
use App\Modules\Auth\Filament\Pages\PilihPeranUnit;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\ListUsers;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lab404\Impersonate\Services\ImpersonateManager;
use Livewire\Livewire;

use function Livewire\store;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('impersonate sungguhan dari daftar pengguna menyimpan posisi trigger ke session', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($superAdmin);

    Livewire::test(ListUsers::class)
        ->callTableAction('impersonate', $timkur)
        ->assertRedirect();

    expect(auth()->user()->id)->toBe($timkur->id)
        ->and(session('impersonate.back_to'))->not->toBeNull();
});

/**
 * leaveImpersonate() dipanggil lewat instansiasi langsung (bukan
 * Livewire::test()->call()) — memanggil ImpersonateManager::leave() di
 * tengah siklus test-request Livewire membuat snapshot/checksum babak
 * lanjutan tidak valid (efek samping regenerasi guard/session, bukan
 * bug pada kode yang diuji). Redirect Livewire (store($page)->get('redirect'))
 * tetap bisa diperiksa langsung tanpa round-trip HTTP palsu itu.
 */
it('meninggalkan impersonate dari gerbang Pilih Peran & Unit kembali ke posisi trigger', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($superAdmin);
    app(ImpersonateManager::class)->take($superAdmin, $timkur);

    session()->put('impersonate.back_to', '/admin/users?filters%5Broles%5D=Dosen+Pengampu');

    $page = new PilihPeranUnit;
    $page->leaveImpersonate();

    expect(store($page)->get('redirect'))->toBe('/admin/users?filters%5Broles%5D=Dosen+Pengampu')
        ->and(session()->has('impersonate.back_to'))->toBeFalse()
        ->and(auth()->user()->id)->toBe($superAdmin->id);
});

it('meninggalkan impersonate dari halaman edit user kembali ke halaman edit tsb', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($superAdmin);
    app(ImpersonateManager::class)->take($superAdmin, $timkur);

    session()->put('impersonate.back_to', "/admin/users/{$timkur->id}/edit");

    $page = new PilihPeranUnit;
    $page->leaveImpersonate();

    expect(store($page)->get('redirect'))->toBe("/admin/users/{$timkur->id}/edit");
});

it('tanpa posisi trigger tersimpan, meninggalkan impersonate tetap fallback ke dashboard', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($superAdmin);
    app(ImpersonateManager::class)->take($superAdmin, $timkur);

    session()->forget('impersonate.back_to');

    $page = new PilihPeranUnit;
    $page->leaveImpersonate();

    expect(store($page)->get('redirect'))->toBe(url('/dashboard'));
});
