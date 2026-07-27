<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Policies\BokPolicy;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
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

    // MkUnit yang mengadaptasi MK asing butuh kurikulum_id milik prodi
    // (lihat CplBokAdaptasiScope::adaptedMkIds()), jadi harus ada Kurikulum
    // untuk prodi supaya BelongsToKurikulumFactory bisa menurunkannya.
    Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Prodi Uji Policy',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('tim kurikulum dengan kelola_bok dapat mengelola bok di prodi', function () {
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();

    expect((new BokPolicy)->manage($this->timkur, $bok))->toBeTrue();
});

it('update() true untuk bok asing yang teradaptasi lewat manageKodeOnly, false bila belum teradaptasi', function () {
    $mkUniv = Mk::factory()->forAcademicUnit($this->univ)->create();
    $bokUniv = Bok::factory()->forAcademicUnit($this->univ)->create();
    $bokUnivLain = Bok::factory()->forAcademicUnit($this->univ)->create();

    $cplBok = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->univ)->create()->id,
        'bok_id' => $bokUniv->id,
    ]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mkUniv->id, 'bobot' => 60]);

    $policy = new BokPolicy;

    expect($policy->update($this->timkur, $bokUniv))->toBeFalse()
        ->and($policy->update($this->timkur, $bokUnivLain))->toBeFalse();

    MkUnit::factory()->forAcademicUnit($this->prodi)->forMk($mkUniv)->create(['is_active' => true]);

    expect($policy->update($this->timkur, $bokUniv))->toBeTrue()
        ->and($policy->manage($this->timkur, $bokUniv))->toBeFalse()
        ->and($policy->update($this->timkur, $bokUnivLain))->toBeFalse();
});

it('delete() tetap false untuk bok asing meski sudah teradaptasi', function () {
    $mkUniv = Mk::factory()->forAcademicUnit($this->univ)->create();
    $bokUniv = Bok::factory()->forAcademicUnit($this->univ)->create();
    $cplUniv = Cpl::factory()->forAcademicUnit($this->univ)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cplUniv->id, 'bok_id' => $bokUniv->id]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mkUniv->id, 'bobot' => 60]);
    MkUnit::factory()->forAcademicUnit($this->prodi)->forMk($mkUniv)->create(['is_active' => true]);

    expect((new BokPolicy)->delete($this->timkur, $bokUniv))->toBeFalse();
});
