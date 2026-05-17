<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('membuat subcpmk via factory for mk cpmk tanpa kolom cpmk_id', function () {
    $this->seed(AcademicUnitSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $mk = Mk::factory()->forAcademicUnit($prodi)->create();
    $cpmk = Cpmk::factory()->forMk($mk)->create();

    $cpl = Cpl::factory()->forAcademicUnit($prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($prodi)->create();
    $cplBok = CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
        'bobot' => 100,
    ]);
    $cplMk = CplMk::query()->create([
        'cpl_bok_id' => $cplBok->id,
        'mk_id' => $mk->id,
        'bobot' => 100,
    ]);

    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();

    expect(Schema::hasColumn('subcpmk', 'cpmk_id'))->toBeFalse();

    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create();

    expect($subcpmk->mk_cpmk_id)->toBe($mkCpmk->id)
        ->and($subcpmk->cpmk)->toBeInstanceOf(Cpmk::class)
        ->and($subcpmk->cpmk->is($cpmk))->toBeTrue();
});
