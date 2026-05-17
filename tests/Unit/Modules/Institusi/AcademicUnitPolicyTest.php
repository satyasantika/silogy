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

it('superadmin boleh viewAny dan update semua tipe unit', function () {
    $user = User::where('username', 'superadmin')->first();
    $prodi = AcademicUnit::where('type', 'study_program')->first();

    expect($this->policy->viewAny($user))->toBeTrue()
        ->and($this->policy->update($user, $prodi))->toBeTrue();
});

it('admin universitas boleh update fakultas tetapi bukan universitas', function () {
    $user = User::where('username', 'adminuniv')->first();
    $fak = AcademicUnit::where('type', 'faculty')->first();
    $univ = AcademicUnit::where('type', 'university')->first();

    expect($this->policy->update($user, $fak))->toBeTrue()
        ->and($this->policy->update($user, $univ))->toBeFalse();
});
