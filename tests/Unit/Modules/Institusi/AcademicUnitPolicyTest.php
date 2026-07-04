<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Policies\AcademicUnitPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->policy = new AcademicUnitPolicy;
});

it('superadmin boleh melihat semua unit dan menghapus unit yang tidak terpakai', function () {
    $user = User::where('username', 'superadmin')->first();
    $prodi = AcademicUnit::where('type', 'study_program')->first();
    $univ = AcademicUnit::where('type', 'university')->first();
    $fakultas = AcademicUnit::where('type', 'faculty')->first();

    $unitKosong = AcademicUnit::factory()->studyProgram($fakultas)->create([
        'nama' => 'Prodi Tanpa Data',
        'code' => 'TANPA',
        'kode_pddikti' => null,
    ]);

    // Universitas punya sub-unit → terlindungi dari penghapusan.
    expect($this->policy->viewAny($user))->toBeTrue()
        ->and($this->policy->view($user, $prodi))->toBeTrue()
        ->and($this->policy->delete($user, $univ))->toBeFalse()
        ->and($this->policy->delete($user, $unitKosong))->toBeTrue();
});

it('auditor boleh viewAny dan view tanpa pivot', function () {
    $user = User::where('username', 'auditor')->first();
    $prodi = AcademicUnit::where('type', 'study_program')->first();

    expect($this->policy->viewAny($user))->toBeTrue()
        ->and($this->policy->view($user, $prodi))->toBeTrue()
        ->and($this->policy->update($user, $prodi))->toBeFalse();
});

it('admin prodi dengan pivot hanya view unit dan ancestor', function () {
    $user = User::where('username', 'adminprodi')->first();
    $prodi = AcademicUnit::where('type', 'study_program')->first();
    $univ = AcademicUnit::where('type', 'university')->first();

    expect($this->policy->view($user, $prodi))->toBeTrue()
        ->and($this->policy->view($user, $univ))->toBeTrue()
        ->and($this->policy->update($user, $univ))->toBeFalse();
});

it('admin fakultas tidak boleh update universitas', function () {
    $user = User::where('username', 'adminfak')->first();
    $univ = AcademicUnit::where('type', 'university')->first();

    expect($this->policy->update($user, $univ))->toBeFalse();
});

it('hanya superadmin yang boleh delete unit yang tidak terpakai', function () {
    $super = User::where('username', 'superadmin')->first();
    $adminProdi = User::where('username', 'adminprodi')->first();
    $fakultas = AcademicUnit::where('type', 'faculty')->first();

    $unitKosong = AcademicUnit::factory()->studyProgram($fakultas)->create([
        'nama' => 'Prodi Kosong Kedua',
        'code' => 'KOSONG2',
        'kode_pddikti' => null,
    ]);

    expect($this->policy->delete($super, $unitKosong))->toBeTrue()
        ->and($this->policy->delete($adminProdi, $unitKosong))->toBeFalse();
});
