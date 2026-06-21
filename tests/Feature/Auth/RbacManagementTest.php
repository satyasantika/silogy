<?php

use App\Models\Permission;
use App\Models\User;
use App\Modules\Auth\Filament\Resources\PermissionResource\Pages\ListPermissions;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\EditUser;
use App\Modules\Auth\Policies\UserPolicy;
use BezhanSalleh\FilamentShield\Resources\Roles\Pages\ListRoles;
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

it('superadmin dapat mengakses halaman role shield', function () {
    $this->actingAs(User::where('username', 'superadmin')->first());

    Livewire::test(ListRoles::class)
        ->assertSuccessful();
});

it('dosen pengampu tidak dapat mengakses halaman role shield', function () {
    $this->actingAs(User::where('username', 'dosen')->first());

    Livewire::test(ListRoles::class)
        ->assertForbidden();
});

it('superadmin dapat mengakses halaman permission', function () {
    $this->actingAs(User::where('username', 'superadmin')->first());

    Livewire::test(ListPermissions::class)
        ->assertSuccessful();
});

it('superadmin dapat menetapkan permission langsung ke pengguna', function () {
    $superadmin = User::where('username', 'superadmin')->first();
    $dosen = User::where('username', 'dosen')->first();
    $permission = Permission::where('name', 'lihat_audit_log')->first();

    $this->actingAs($superadmin);

    Livewire::test(EditUser::class, ['record' => $dosen->getRouteKey()])
        ->fillForm([
            'permissions' => [$permission->id],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $dosen->refresh();

    expect($dosen->hasDirectPermission('lihat_audit_log'))->toBeTrue();
});

it('admin prodi tidak dapat menetapkan permission langsung ke pengguna', function () {
    $adminProdi = User::where('username', 'adminprodi')->first();
    $dosen = User::where('username', 'dosen')->first();
    $permission = Permission::where('name', 'lihat_audit_log')->first();

    expect((new UserPolicy)->assignPermissions($adminProdi, $dosen))->toBeFalse();

    $this->actingAs($adminProdi);

    Livewire::test(EditUser::class, ['record' => $dosen->getRouteKey()])
        ->assertSuccessful()
        ->assertFormFieldIsHidden('permissions');

    $dosen->givePermissionTo($permission);

    Livewire::test(EditUser::class, ['record' => $dosen->getRouteKey()])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($dosen->fresh()->hasDirectPermission('lihat_audit_log'))->toBeTrue();
});

it('hanya superadmin yang boleh impersonate pengguna lain', function () {
    $superadmin = User::where('username', 'superadmin')->first();
    $dosen = User::where('username', 'dosen')->first();
    $adminProdi = User::where('username', 'adminprodi')->first();

    expect($superadmin->canImpersonate())->toBeTrue()
        ->and($adminProdi->canImpersonate())->toBeFalse()
        ->and($dosen->canBeImpersonated())->toBeTrue()
        ->and($superadmin->canBeImpersonated())->toBeFalse();
});

it('superadmin dapat impersonate dosen via manager', function () {
    $superadmin = User::where('username', 'superadmin')->first();
    $dosen = User::where('username', 'dosen')->first();

    $this->actingAs($superadmin);

    $manager = app(ImpersonateManager::class);

    expect($manager->take($superadmin, $dosen, 'web'))->toBeTrue()
        ->and(auth()->id())->toBe($dosen->id)
        ->and($manager->isImpersonating())->toBeTrue();

    expect($manager->leave())->toBeTrue()
        ->and(auth()->id())->toBe($superadmin->id);
});
