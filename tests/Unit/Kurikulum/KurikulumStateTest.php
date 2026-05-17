<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\StateTransition;
use App\Modules\Kurikulum\States\CplState;
use App\Modules\Kurikulum\States\DraftState;
use App\Modules\Kurikulum\States\ProfilLulusanState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createKurikulumForUnit(AcademicUnit $unit): Kurikulum
{
    return Kurikulum::query()->create([
        'academic_unit_id' => $unit->id,
        'nama' => 'Kurikulum Test',
        'tahun' => 2025,
        'state' => DraftState::class,
        'is_active' => false,
    ]);
}

it('cannot transition to cpl before profil lulusan for prodi', function () {
    $univ = AcademicUnit::factory()->university()->create();
    $fak = AcademicUnit::factory()->faculty($univ)->create();
    $jur = AcademicUnit::factory()->department($fak)->create();
    $prodi = AcademicUnit::factory()->studyProgram($jur)->create();

    $kurikulum = createKurikulumForUnit($prodi);
    $kurikulum->load('academicUnit');

    expect($kurikulum->state->canTransitionTo(CplState::class))->toBeFalse();
});

it('can skip profil lulusan for non prodi', function () {
    $univ = AcademicUnit::factory()->university()->create();
    $fak = AcademicUnit::factory()->faculty($univ)->create();

    $kurikulum = createKurikulumForUnit($fak);
    $kurikulum->load('academicUnit');

    expect($kurikulum->state->canTransitionTo(CplState::class))->toBeTrue();
});

it('logs state transition', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $univ = AcademicUnit::factory()->university()->create();
    $fak = AcademicUnit::factory()->faculty($univ)->create();
    $jur = AcademicUnit::factory()->department($fak)->create();
    $prodi = AcademicUnit::factory()->studyProgram($jur)->create();

    $kurikulum = createKurikulumForUnit($prodi);
    $kurikulum->load('academicUnit');

    $kurikulum->state->transitionTo(ProfilLulusanState::class);

    $log = StateTransition::query()
        ->where('model_type', Kurikulum::class)
        ->where('model_id', $kurikulum->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->from_state)->toBe('draft')
        ->and($log->to_state)->toBe('profil_lulusan')
        ->and($log->actor_id)->toBe($user->id);
});
