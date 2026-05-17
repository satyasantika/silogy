<?php

use App\Models\User;
use App\Modules\Auth\Policies\UserPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->policy = new UserPolicy;
});

it('superadmin boleh mengelola pengguna', function () {
    $user = User::where('username', 'superadmin')->first();
    $target = User::where('username', 'dosen')->first();

    expect($this->policy->viewAny($user))->toBeTrue()
        ->and($this->policy->create($user))->toBeTrue()
        ->and($this->policy->update($user, $target))->toBeTrue();
});

it('admin prodi boleh viewAny via kelola_user_prodi', function () {
    $user = User::where('username', 'adminprodi')->first();
    $dosen = User::where('username', 'dosen')->first();

    expect($this->policy->viewAny($user))->toBeTrue()
        ->and($this->policy->update($user, $dosen))->toBeTrue();
});

it('dosen pengampu tidak boleh mengelola pengguna', function () {
    $user = User::where('username', 'dosen')->first();

    expect($this->policy->viewAny($user))->toBeFalse();
});

it('admin universitas tidak boleh update dosen tanpa pivot scope bersama', function () {
    $adminUniv = User::where('username', 'adminuniv')->first();
    $auditor = User::where('username', 'auditor')->first();

    expect($this->policy->update($adminUniv, $auditor))->toBeFalse();
});
