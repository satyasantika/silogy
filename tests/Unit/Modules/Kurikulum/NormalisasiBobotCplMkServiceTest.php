<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Services\NormalisasiBobotCplMkService;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);

    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $this->cplBok = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create()->id,
    ]);

    $this->service = new NormalisasiBobotCplMkService;
});

it('status kosong bila belum ada baris cpl_mk', function () {
    $hasil = $this->service->normalisasi($this->cplBok, $this->prodi->id);

    expect($hasil['status'])->toBe('kosong');
});

it('semua baris milik prodi dinormalisasi tepat ke 100', function () {
    $mk1 = Mk::factory()->forAcademicUnit($this->prodi)->create();
    $mk2 = Mk::factory()->forAcademicUnit($this->prodi)->create();

    $row1 = CplMk::query()->create(['cpl_bok_id' => $this->cplBok->id, 'mk_id' => $mk1->id, 'bobot' => 30]);
    $row2 = CplMk::query()->create(['cpl_bok_id' => $this->cplBok->id, 'mk_id' => $mk2->id, 'bobot' => 30]);

    $hasil = $this->service->normalisasi($this->cplBok, $this->prodi->id);

    expect($hasil['status'])->toBe('dinormalisasi')
        ->and($hasil['total_sebelum'])->toBe(60.0)
        ->and($hasil['total_terkunci'])->toBe(0.0)
        ->and((float) $row1->fresh()->bobot + (float) $row2->fresh()->bobot)->toBe(100.0)
        ->and((float) $row1->fresh()->bobot)->toBe(50.0);
});

it('baris terkunci (mk unit lain) dibiarkan, hanya baris milik prodi diredistribusi ke sisa target', function () {
    $cplBokUniv = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->univ)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->univ)->create()->id,
    ]);

    $mkUniv = Mk::factory()->forAcademicUnit($this->univ)->create();
    $mkProdi1 = Mk::factory()->forAcademicUnit($this->prodi)->create();
    $mkProdi2 = Mk::factory()->forAcademicUnit($this->prodi)->create();

    MkUnit::factory()->forAcademicUnit($this->prodi)->forMk($mkUniv)->create(['is_active' => true]);

    $rowLocked = CplMk::query()->create(['cpl_bok_id' => $cplBokUniv->id, 'mk_id' => $mkUniv->id, 'bobot' => 40]);
    $rowEditable1 = CplMk::query()->create(['cpl_bok_id' => $cplBokUniv->id, 'mk_id' => $mkProdi1->id, 'bobot' => 20]);
    $rowEditable2 = CplMk::query()->create(['cpl_bok_id' => $cplBokUniv->id, 'mk_id' => $mkProdi2->id, 'bobot' => 20]);

    $hasil = $this->service->normalisasi($cplBokUniv, $this->prodi->id);

    expect($hasil['status'])->toBe('dinormalisasi')
        ->and($hasil['total_terkunci'])->toBe(40.0)
        ->and((float) $rowLocked->fresh()->bobot)->toBe(40.0) // tidak disentuh
        ->and((float) $rowEditable1->fresh()->bobot)->toBe(30.0)
        ->and((float) $rowEditable2->fresh()->bobot)->toBe(30.0);
});

it('status terkunci bila baris terkunci saja sudah >= 100, tidak ada baris yang berubah', function () {
    $cplBokUniv = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->univ)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->univ)->create()->id,
    ]);

    $mkUniv = Mk::factory()->forAcademicUnit($this->univ)->create();
    $mkProdi = Mk::factory()->forAcademicUnit($this->prodi)->create();

    MkUnit::factory()->forAcademicUnit($this->prodi)->forMk($mkUniv)->create(['is_active' => true]);

    $rowLocked = CplMk::query()->create(['cpl_bok_id' => $cplBokUniv->id, 'mk_id' => $mkUniv->id, 'bobot' => 100]);
    $rowEditable = CplMk::query()->create(['cpl_bok_id' => $cplBokUniv->id, 'mk_id' => $mkProdi->id, 'bobot' => 20]);

    $hasil = $this->service->normalisasi($cplBokUniv, $this->prodi->id);

    expect($hasil['status'])->toBe('terkunci')
        ->and((float) $rowLocked->fresh()->bobot)->toBe(100.0)
        ->and((float) $rowEditable->fresh()->bobot)->toBe(20.0);
});

it('status sudah_pas bila total sudah tepat 100', function () {
    $mk = Mk::factory()->forAcademicUnit($this->prodi)->create();
    CplMk::query()->create(['cpl_bok_id' => $this->cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);

    $hasil = $this->service->normalisasi($this->cplBok, $this->prodi->id);

    expect($hasil['status'])->toBe('sudah_pas');
});
