<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Services\NormalisasiBobotCplMkService;
use App\Modules\MK\Models\Mk;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);

    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $this->mk = Mk::factory()->forAcademicUnit($this->prodi)->create();

    $this->service = new NormalisasiBobotCplMkService;
});

it('status kosong bila belum ada baris cpl_mk', function () {
    $hasil = $this->service->normalisasi($this->mk, $this->prodi->id);

    expect($hasil['status'])->toBe('kosong');
});

it('semua baris milik prodi dinormalisasi tepat ke 100, walau kolom cplbok bukan milik prodi', function () {
    $cplBokProdi = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create()->id,
    ]);
    $cplBokUniv = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->univ)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->univ)->create()->id,
    ]);

    $row1 = CplMk::query()->create(['cpl_bok_id' => $cplBokProdi->id, 'mk_id' => $this->mk->id, 'bobot' => 30]);
    $row2 = CplMk::query()->create(['cpl_bok_id' => $cplBokUniv->id, 'mk_id' => $this->mk->id, 'bobot' => 30]);

    $hasil = $this->service->normalisasi($this->mk, $this->prodi->id);

    expect($hasil['status'])->toBe('dinormalisasi')
        ->and($hasil['total_sebelum'])->toBe(60.0)
        ->and($hasil['total_terkunci'])->toBe(0.0)
        ->and((float) $row1->fresh()->bobot + (float) $row2->fresh()->bobot)->toBe(100.0)
        ->and((float) $row1->fresh()->bobot)->toBe(50.0);
});

it('mk milik unit lain terkunci sepenuhnya, walau salah satu kolom cplbok milik prodi', function () {
    // canEditCplMkCell() kini murni berbasis kepemilikan MK — kolom CplBok
    // tidak lagi berpengaruh, jadi seluruh baris MK unit lain ini terkunci
    // sekaligus, tanpa redistribusi parsial.
    $mkUniv = Mk::factory()->forAcademicUnit($this->univ)->create();

    $cplBokProdi = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create()->id,
    ]);
    $cplBokUniv = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->univ)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->univ)->create()->id,
    ]);

    $rowProdiSisi = CplMk::query()->create(['cpl_bok_id' => $cplBokProdi->id, 'mk_id' => $mkUniv->id, 'bobot' => 40]);
    $rowUnivSisi = CplMk::query()->create(['cpl_bok_id' => $cplBokUniv->id, 'mk_id' => $mkUniv->id, 'bobot' => 20]);

    $hasil = $this->service->normalisasi($mkUniv, $this->prodi->id);

    expect($hasil['status'])->toBe('terkunci')
        ->and($hasil['total_terkunci'])->toBe(60.0)
        ->and((float) $rowProdiSisi->fresh()->bobot)->toBe(40.0)
        ->and((float) $rowUnivSisi->fresh()->bobot)->toBe(20.0);
});

it('mk unit lain tetap terkunci meski totalnya sudah tepat 100', function () {
    // Kepemilikan MK menentukan editabilitas — total 100 tidak mengubah status
    // terkunci menjadi sudah_pas bagi unit yang tidak punya hak edit.
    $mkUniv = Mk::factory()->forAcademicUnit($this->univ)->create();

    $cplBokProdi = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create()->id,
    ]);
    CplMk::query()->create(['cpl_bok_id' => $cplBokProdi->id, 'mk_id' => $mkUniv->id, 'bobot' => 100]);

    $hasil = $this->service->normalisasi($mkUniv, $this->prodi->id);

    expect($hasil['status'])->toBe('terkunci')
        ->and($hasil['total_terkunci'])->toBe(100.0);
});

it('status sudah_pas bila total sudah tepat 100', function () {
    $cplBok = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create()->id,
    ]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $this->mk->id, 'bobot' => 100]);

    $hasil = $this->service->normalisasi($this->mk, $this->prodi->id);

    expect($hasil['status'])->toBe('sudah_pas');
});
