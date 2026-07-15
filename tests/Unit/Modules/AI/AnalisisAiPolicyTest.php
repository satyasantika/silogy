<?php

use App\Models\User;
use App\Modules\AI\Policies\AnalisisAiPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->policy = new AnalisisAiPolicy;
});

it('allows pimpinan with minta_analisis_ai to view and create', function () {
    $rektor = User::where('username', 'rektor')->firstOrFail();

    expect($this->policy->viewAny($rektor))->toBeTrue()
        ->and($this->policy->create($rektor))->toBeTrue();
});

it('allows auditor to view but not create', function () {
    $auditor = User::where('username', 'auditor')->firstOrFail();

    expect($this->policy->viewAny($auditor))->toBeTrue()
        ->and($this->policy->create($auditor))->toBeFalse();
});

it('denies dosen from ai module', function () {
    $dosen = User::where('username', 'dosen')->firstOrFail();

    expect($this->policy->viewAny($dosen))->toBeFalse()
        ->and($this->policy->create($dosen))->toBeFalse();
});

it('denies super admin from ai module — delegated to operational roles', function () {
    $superadmin = User::where('username', 'superadmin')->firstOrFail();

    expect($this->policy->viewAny($superadmin))->toBeFalse()
        ->and($this->policy->create($superadmin))->toBeFalse();
});
