<?php

use App\Models\User;
use App\Modules\Auth\Livewire\RoleSwitcher;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Kurikulum\Policies\KurikulumPolicy;
use App\Modules\Penilaian\Policies\InputNilaiPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

function timkurSegar(): User
{
    return User::query()->where('username', 'timkur')->firstOrFail();
}

it('timkur dual role: tanpa role aktif semua kemampuan berlaku', function () {
    $timkur = timkurSegar();
    $this->actingAs($timkur);

    expect($timkur->hasRole('Tim Kurikulum'))->toBeTrue()
        ->and($timkur->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and(app(KurikulumPolicy::class)->viewAny($timkur))->toBeTrue()
        ->and(app(InputNilaiPolicy::class)->access($timkur))->toBeTrue();
});

it('memilih role dosen menyembunyikan kemampuan tim kurikulum', function () {
    $this->actingAs(timkurSegar());

    Livewire::test(RoleSwitcher::class)
        ->set('activeRole', 'Dosen Pengampu');

    $timkur = timkurSegar();

    expect(session(ActiveRole::SESSION_KEY))->toBe('Dosen Pengampu')
        ->and($timkur->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and($timkur->hasRole('Tim Kurikulum'))->toBeFalse()
        ->and(app(KurikulumPolicy::class)->viewAny($timkur))->toBeFalse()
        ->and(app(InputNilaiPolicy::class)->access($timkur))->toBeTrue();
});

it('memilih role tim kurikulum menyembunyikan kemampuan dosen', function () {
    $this->actingAs(timkurSegar());

    Livewire::test(RoleSwitcher::class)
        ->set('activeRole', 'Tim Kurikulum');

    $timkur = timkurSegar();

    expect($timkur->hasRole('Tim Kurikulum'))->toBeTrue()
        ->and($timkur->hasRole('Dosen Pengampu'))->toBeFalse()
        ->and(app(KurikulumPolicy::class)->viewAny($timkur))->toBeTrue()
        ->and(app(InputNilaiPolicy::class)->access($timkur))->toBeFalse();
});

it('kembali ke semua peran memulihkan seluruh kemampuan', function () {
    $this->actingAs(timkurSegar());

    session()->put(ActiveRole::SESSION_KEY, 'Dosen Pengampu');

    Livewire::test(RoleSwitcher::class)
        ->set('activeRole', '');

    $timkur = timkurSegar();

    expect(session()->has(ActiveRole::SESSION_KEY))->toBeFalse()
        ->and($timkur->hasRole('Tim Kurikulum'))->toBeTrue()
        ->and($timkur->hasRole('Dosen Pengampu'))->toBeTrue();
});

it('role yang tidak dimiliki diabaikan', function () {
    $timkur = timkurSegar();
    $this->actingAs($timkur);

    session()->put(ActiveRole::SESSION_KEY, 'Super Admin');

    expect(ActiveRole::currentFor($timkur))->toBeNull()
        ->and(timkurSegar()->hasRole('Tim Kurikulum'))->toBeTrue()
        ->and(timkurSegar()->hasRole('Super Admin'))->toBeFalse();
});

it('filter role aktif tidak mempengaruhi pengecekan role user lain', function () {
    $this->actingAs(timkurSegar());
    session()->put(ActiveRole::SESSION_KEY, 'Dosen Pengampu');

    $superadmin = User::query()->where('username', 'superadmin')->firstOrFail();

    expect($superadmin->hasRole('Super Admin'))->toBeTrue();
});

it('switcher tampil untuk user multi-role dan tersembunyi untuk single role', function () {
    $this->actingAs(timkurSegar());

    Livewire::test(RoleSwitcher::class)
        ->assertSee('Peran aktif')
        ->assertSee('Semua peran')
        ->assertSee('Tim Kurikulum')
        ->assertSee('Dosen Pengampu');

    $this->actingAs(User::query()->where('username', 'dosen')->firstOrFail());

    Livewire::test(RoleSwitcher::class)
        ->assertDontSee('Peran aktif');
});
