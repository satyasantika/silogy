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

it('pengaturan pengguna eksklusif superadmin: admin unit tidak boleh', function () {
    $dosen = User::where('username', 'dosen')->first();

    foreach (['adminuniv', 'adminfak', 'adminjur', 'adminprodi'] as $username) {
        $admin = User::where('username', $username)->first();

        expect($this->policy->viewAny($admin))->toBeFalse()
            ->and($this->policy->create($admin))->toBeFalse()
            ->and($this->policy->update($admin, $dosen))->toBeFalse()
            ->and($this->policy->delete($admin, $dosen))->toBeFalse();
    }
});

it('dosen pengampu tidak boleh melihat maupun mengelola pengguna', function () {
    $user = User::where('username', 'dosen')->first();
    $target = User::where('username', 'korma')->first();

    expect($this->policy->viewAny($user))->toBeFalse()
        ->and($this->policy->view($user, $target))->toBeFalse()
        ->and($this->policy->update($user, $target))->toBeFalse();
});
