<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Policies\KurikulumPolicy;
use App\Modules\Kurikulum\States\DraftState;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('mengizinkan tim kurikulum mengelola kurikulum di unitnya', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $timkur = User::query()->where('username', 'timkur')->first();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum IF 2025',
        'tahun' => 2025,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    $policy = new KurikulumPolicy;

    expect($policy->manage($timkur, $kurikulum))->toBeTrue();
});

it('menolak user tanpa status tim kurikulum pada unit', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $admin = User::query()->where('username', 'adminprodi')->first();

    AcademicUnitUser::query()
        ->where('user_id', $admin->id)
        ->where('academic_unit_id', $prodi->id)
        ->update(['status_tim_kurikulum' => false]);

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum IF 2025',
        'tahun' => 2025,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    $policy = new KurikulumPolicy;

    expect($policy->manage($admin, $kurikulum))->toBeFalse();
});
