<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\CPL\Policies\CplPolicy;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
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
    $this->policy = new CplPolicy;
});

it('cpl belum diinteraksikan bila belum dipetakan ke profil maupun bok', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();

    expect($cpl->belumDiinteraksikan())->toBeTrue()
        ->and($this->policy->delete($this->timkur, $cpl))->toBeTrue();
});

it('cpl sudah diinteraksikan bila dipetakan ke profil lulusan', function () {
    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji',
        'tahun' => 2026,
    ]);

    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-1',
        'deskripsi' => 'Deskripsi profil',
    ]);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();

    CplProfilLulusan::query()->create([
        'cpl_id' => $cpl->id,
        'profil_lulusan_id' => $profil->id,
    ]);

    expect($cpl->fresh()->belumDiinteraksikan())->toBeFalse()
        ->and($this->policy->delete($this->timkur, $cpl))->toBeFalse();
});

it('cpl sudah diinteraksikan bila dipetakan ke bok', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();

    CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
    ]);

    expect($cpl->fresh()->belumDiinteraksikan())->toBeFalse()
        ->and($this->policy->delete($this->timkur, $cpl))->toBeFalse();
});
