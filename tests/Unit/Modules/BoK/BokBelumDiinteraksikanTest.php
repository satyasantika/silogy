<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Policies\BokPolicy;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\Institusi\Models\AcademicUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->policy = new BokPolicy;
});

it('bok belum diinteraksikan bila belum dipetakan ke cpl', function () {
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();

    expect($bok->belumDiinteraksikan())->toBeTrue()
        ->and($this->policy->delete($this->timkur, $bok))->toBeTrue();
});

it('bok sudah diinteraksikan bila dipetakan ke cpl', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();

    CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
    ]);

    expect($bok->fresh()->belumDiinteraksikan())->toBeFalse()
        ->and($this->policy->delete($this->timkur, $bok))->toBeFalse();
});
