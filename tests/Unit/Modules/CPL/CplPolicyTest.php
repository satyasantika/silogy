<?php

use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Policies\CplPolicy;
use App\Modules\Institusi\Models\AcademicUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('tim kurikulum dengan kelola_cpl dapat mengelola cpl di prodi', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $timkur = User::query()->where('username', 'timkur')->first();
    $cpl = Cpl::factory()->forAcademicUnit($prodi)->create();

    expect((new CplPolicy)->manage($timkur, $cpl))->toBeTrue();
});
