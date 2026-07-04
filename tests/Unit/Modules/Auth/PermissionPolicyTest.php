<?php

use App\Models\Permission;
use App\Models\User;
use App\Modules\Auth\Policies\PermissionPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->policy = new PermissionPolicy;
});

it('superadmin dengan kelola_permission boleh melihat daftar permission', function () {
    $user = User::where('username', 'superadmin')->first();
    $permission = Permission::where('name', 'kelola_cpl')->first();

    expect($this->policy->viewAny($user))->toBeTrue()
        ->and($this->policy->view($user, $permission))->toBeTrue();
});

it('dosen pengampu tidak boleh melihat daftar permission', function () {
    $user = User::where('username', 'dosen')->first();
    $permission = Permission::where('name', 'kelola_cpl')->first();

    expect($this->policy->viewAny($user))->toBeFalse()
        ->and($this->policy->view($user, $permission))->toBeFalse();
});

it('permission tidak boleh dibuat atau dihapus via policy', function () {
    $user = User::where('username', 'superadmin')->first();
    $permission = Permission::where('name', 'kelola_cpl')->first();

    expect($this->policy->create($user))->toBeFalse()
        ->and($this->policy->delete($user, $permission))->toBeFalse();
});
