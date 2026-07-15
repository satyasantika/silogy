<?php

use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\CPL\Policies\CplPolicy;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
});

it('tim kurikulum dengan kelola_cpl dapat mengelola cpl di prodi', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $timkur = User::query()->where('username', 'timkur')->first();
    $cpl = Cpl::factory()->forAcademicUnit($prodi)->create();

    expect((new CplPolicy)->manage($timkur, $cpl))->toBeTrue();
});

it('update() true untuk cpl asing yang teradaptasi lewat manageKodeOnly, false bila belum teradaptasi', function () {
    $mkUniv = Mk::factory()->forAcademicUnit($this->univ)->create();
    $cplUniv = Cpl::factory()->forAcademicUnit($this->univ)->create();
    $cplUnivLain = Cpl::factory()->forAcademicUnit($this->univ)->create();

    $cplBok = CplBok::query()->create([
        'cpl_id' => $cplUniv->id,
        'bok_id' => \App\Modules\BoK\Models\Bok::factory()->forAcademicUnit($this->univ)->create()->id,
    ]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mkUniv->id, 'bobot' => 60]);

    $policy = new CplPolicy;

    // Belum diadaptasi sama sekali -> manage() dan manageKodeOnly() dua-duanya false.
    expect($policy->update($this->timkur, $cplUniv))->toBeFalse()
        ->and($policy->update($this->timkur, $cplUnivLain))->toBeFalse();

    MkUnit::factory()->forAcademicUnit($this->prodi)->forMk($mkUniv)->create(['is_active' => true]);

    // Setelah diadaptasi: cplUniv (terhubung mkUniv) -> true lewat manageKodeOnly();
    // cplUnivLain (tidak terhubung MK manapun yang diadaptasi) -> tetap false.
    expect($policy->update($this->timkur, $cplUniv))->toBeTrue()
        ->and($policy->manage($this->timkur, $cplUniv))->toBeFalse()
        ->and($policy->update($this->timkur, $cplUnivLain))->toBeFalse();
});

it('delete() tetap false untuk cpl asing meski sudah teradaptasi', function () {
    $mkUniv = Mk::factory()->forAcademicUnit($this->univ)->create();
    $cplUniv = Cpl::factory()->forAcademicUnit($this->univ)->create();
    $bokUniv = \App\Modules\BoK\Models\Bok::factory()->forAcademicUnit($this->univ)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cplUniv->id, 'bok_id' => $bokUniv->id]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mkUniv->id, 'bobot' => 60]);
    MkUnit::factory()->forAcademicUnit($this->prodi)->forMk($mkUniv)->create(['is_active' => true]);

    expect((new CplPolicy)->delete($this->timkur, $cplUniv))->toBeFalse();
});
