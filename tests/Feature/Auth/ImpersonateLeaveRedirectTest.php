<?php

use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\ListUsers;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Lab404\Impersonate\Services\ImpersonateManager;
use Livewire\Livewire;

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
 * /impersonate/leave adalah route GET biasa (bukan aksi Livewire) — lihat
 * catatan di LeaveImpersonateController. Karena itu ditest lewat HTTP
 * request sungguhan ($this->get()), bukan Livewire::test(), supaya
 * regenerasi session/CSRF token yang terjadi di dalam ImpersonateManager::leave()
 * teruji dalam siklus request yang sama seperti di production.
 */
it('meninggalkan impersonate dari gerbang Pilih Peran & Unit kembali ke posisi trigger', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($superAdmin);
    app(ImpersonateManager::class)->take($superAdmin, $timkur);

    session()->put('impersonate.back_to', '/admin/users?filters%5Broles%5D=Dosen+Pengampu');
    session()->put(ActiveRole::SESSION_KEY, 'Dosen Pengampu');
    session()->put(AcademicUnitTerpilih::SESSION_KEY, 'unit-id');

    $this->get(route('impersonate.leave'))
        ->assertRedirect('/admin/users?filters%5Broles%5D=Dosen+Pengampu');

    expect(session()->has('impersonate.back_to'))->toBeFalse()
        ->and(session()->has(ActiveRole::SESSION_KEY))->toBeFalse()
        ->and(session()->has(AcademicUnitTerpilih::SESSION_KEY))->toBeFalse()
        ->and(auth()->user()->id)->toBe($superAdmin->id);
});

it('meninggalkan impersonate dari halaman edit user kembali ke halaman edit tsb', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($superAdmin);
    app(ImpersonateManager::class)->take($superAdmin, $timkur);

    session()->put('impersonate.back_to', "/admin/users/{$timkur->id}/edit");

    $this->get(route('impersonate.leave'))
        ->assertRedirect("/admin/users/{$timkur->id}/edit");
});

it('tanpa posisi trigger tersimpan, meninggalkan impersonate tetap fallback ke dashboard', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($superAdmin);
    app(ImpersonateManager::class)->take($superAdmin, $timkur);

    session()->forget('impersonate.back_to');

    $this->get(route('impersonate.leave'))
        ->assertRedirect(url('/dashboard'));
});

it('/impersonate/leave tanpa sedang impersonate cukup fallback ke dashboard, tidak error', function () {
    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();

    $this->actingAs($superAdmin);

    $this->get(route('impersonate.leave'))
        ->assertRedirect(url('/dashboard'));
});
