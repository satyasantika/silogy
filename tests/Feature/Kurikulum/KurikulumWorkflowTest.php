<?php

use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\States\BokState;
use App\Modules\Kurikulum\States\CplState;
use App\Modules\Kurikulum\States\DraftState;
use App\Modules\Kurikulum\States\ProfilLulusanState;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('workflow prodi naik dari draft ke cpl setelah profil dan pemetaan cpl', function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $timkur = User::query()->where('username', 'timkur')->first();

    $this->actingAs($timkur);

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Test IF',
        'kode' => 'KUR-2025-IF',
        'tahun' => 2025,
        'target_capaian_lulusan' => 75,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    expect($kurikulum->state->getValue())->toBe('draft');

    $kurikulum->state->transitionTo(ProfilLulusanState::class);
    $kurikulum->refresh();
    expect($kurikulum->state->getValue())->toBe('profil_lulusan');

    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-01',
        'nama' => 'Data Scientist',
        'deskripsi' => 'Mampu menganalisis data',
        'urutan' => 1,
    ]);

    ProfilIndikator::query()->create([
        'profil_id' => $profil->id,
        'nama' => 'Indikator 1',
        'deskripsi' => 'Deskripsi indikator',
    ]);

    expect($kurikulum->state->canTransitionTo(CplState::class))->toBeTrue();

    $kurikulum->state->transitionTo(CplState::class);
    $kurikulum->refresh();
    expect($kurikulum->state->getValue())->toBe('cpl');

    $cpl = Cpl::query()->create([
        'academic_unit_id' => $prodi->id,
        'kode' => 'CPL-01',
        'deskripsi' => 'CPL uji workflow',
        'domain' => 'kognitif',
    ]);

    CplProfilLulusan::query()->create([
        'cpl_id' => $cpl->id,
        'profil_lulusan_id' => $profil->id,
    ]);

    expect($kurikulum->fresh()->state->getValue())->toBe('bok');
});
