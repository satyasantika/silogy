<?php

use App\Models\User;
use App\Modules\Auth\Policies\UserPolicy;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
    $this->policy = new UserPolicy;
});

it('superadmin boleh mengelola pengguna', function () {
    $user = User::where('username', 'superadmin')->first();

    expect($this->policy->viewAny($user))->toBeTrue()
        ->and($this->policy->create($user))->toBeTrue();
});

it('admin prodi boleh mengelola pengguna via kelola_user_prodi', function () {
    $user = User::where('username', 'adminprodi')->first();

    expect($this->policy->viewAny($user))->toBeTrue();
});

it('dosen pengampu tidak boleh mengelola pengguna', function () {
    $user = User::where('username', 'dosen')->first();

    expect($this->policy->viewAny($user))->toBeFalse();
});
