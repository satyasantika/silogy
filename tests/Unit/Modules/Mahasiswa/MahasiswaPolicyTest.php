<?php

use App\Models\User;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Mahasiswa\Policies\MahasiswaPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\MahasiswaSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(MahasiswaSeeder::class);
    $this->policy = new MahasiswaPolicy;
});

it('admin prodi dapat viewAny dan update mahasiswa di prodi sendiri', function () {
    $admin = User::where('username', 'adminprodi')->first();
    $mahasiswa = Mahasiswa::query()->first();

    expect($this->policy->viewAny($admin))->toBeTrue()
        ->and($this->policy->update($admin, $mahasiswa))->toBeTrue();
});

it('dekan dapat viewAny dan view mahasiswa di fakultasnya', function () {
    $dekan = User::where('username', 'dekan')->first();
    $mahasiswa = Mahasiswa::query()->first();

    expect($this->policy->viewAny($dekan))->toBeTrue()
        ->and($this->policy->view($dekan, $mahasiswa))->toBeTrue();
});

it('hanya super admin dan admin universitas yang boleh delete', function () {
    $super = User::where('username', 'superadmin')->first();
    $adminUniv = User::where('username', 'adminuniv')->first();
    $adminProdi = User::where('username', 'adminprodi')->first();
    $mahasiswa = Mahasiswa::query()->first();

    expect($this->policy->delete($super, $mahasiswa))->toBeTrue()
        ->and($this->policy->delete($adminUniv, $mahasiswa))->toBeTrue()
        ->and($this->policy->delete($adminProdi, $mahasiswa))->toBeFalse();
});

it('dosen tidak dapat viewAny mahasiswa', function () {
    $dosen = User::where('username', 'dosen')->first();

    expect($this->policy->viewAny($dosen))->toBeFalse();
});
