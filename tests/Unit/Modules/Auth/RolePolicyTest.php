<?php

use App\Models\User;
use App\Policies\RolePolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->policy = new RolePolicy;
});

it('superadmin dengan kelola_role boleh mengelola role', function () {
    $user = User::where('username', 'superadmin')->first();
    $role = Role::where('name', 'Dosen Pengampu')->first();

    expect($this->policy->viewAny($user))->toBeTrue()
        ->and($this->policy->update($user, $role))->toBeTrue()
        ->and($this->policy->create($user))->toBeTrue();
});

it('dosen pengampu tidak boleh mengelola role', function () {
    $user = User::where('username', 'dosen')->first();
    $role = Role::where('name', 'Dosen Pengampu')->first();

    expect($this->policy->viewAny($user))->toBeFalse()
        ->and($this->policy->update($user, $role))->toBeFalse();
});

it('role super admin tidak boleh dihapus', function () {
    $user = User::where('username', 'superadmin')->first();
    $role = Role::where('name', 'Super Admin')->first();

    expect($this->policy->delete($user, $role))->toBeFalse();
});
