<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\MK\Models\Mk;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->mk = Mk::factory()->forAcademicUnit($this->prodi)->create();

    $this->cplBokA = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create()->id,
    ]);
    $this->cplBokB = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create()->id,
    ]);
});

it('sisa kuota mk adalah 100 bila belum ada interaksi', function () {
    expect(CplMk::sisaBobotTersedia($this->mk->id))->toBe(100.0);
});

it('sisa kuota mk mengurangi bobot yang sudah terpakai', function () {
    CplMk::query()->create([
        'mk_id' => $this->mk->id,
        'cpl_bok_id' => $this->cplBokA->id,
        'bobot' => 60,
    ]);

    expect(CplMk::sisaBobotTersedia($this->mk->id))->toBe(40.0)
        ->and(CplMk::sisaBobotTersedia($this->mk->id, $this->cplBokA->id))->toBe(100.0)
        ->and(CplMk::sisaBobotTersedia($this->mk->id, $this->cplBokB->id))->toBe(40.0);
});

it('sisa kuota tidak negatif bila total sudah 100', function () {
    CplMk::query()->create([
        'mk_id' => $this->mk->id,
        'cpl_bok_id' => $this->cplBokA->id,
        'bobot' => 100,
    ]);

    expect(CplMk::sisaBobotTersedia($this->mk->id))->toBe(0.0)
        ->and(CplMk::sisaBobotTersedia($this->mk->id, $this->cplBokB->id))->toBe(0.0);
});

it('sisa kuota dibulatkan ke satu desimal', function () {
    CplMk::query()->create([
        'mk_id' => $this->mk->id,
        'cpl_bok_id' => $this->cplBokA->id,
        'bobot' => 33.3,
    ]);

    expect(CplMk::sisaBobotTersedia($this->mk->id))->toBe(66.7);
});

it('rekap pas hanya bila tepat 100', function () {
    expect(CplMk::rekapPas(100.0))->toBeTrue()
        ->and(CplMk::rekapPas(99.9))->toBeFalse()
        ->and(CplMk::rekapPas(100.1))->toBeFalse()
        ->and(CplMk::rekapPas(99.8))->toBeFalse();
});

it('sisa kuota tiga cpl hampir merata memakai skala satu desimal', function () {
    $cplBokC = CplBok::query()->create([
        'cpl_id' => Cpl::factory()->forAcademicUnit($this->prodi)->create()->id,
        'bok_id' => Bok::factory()->forAcademicUnit($this->prodi)->create()->id,
    ]);

    CplMk::query()->create(['mk_id' => $this->mk->id, 'cpl_bok_id' => $this->cplBokA->id, 'bobot' => 33.30]);
    CplMk::query()->create(['mk_id' => $this->mk->id, 'cpl_bok_id' => $this->cplBokB->id, 'bobot' => 33.33]);
    CplMk::query()->create(['mk_id' => $this->mk->id, 'cpl_bok_id' => $cplBokC->id, 'bobot' => 33.34]);

    expect(CplMk::jumlahBobot([33.30, 33.33, 33.34]))->toBe(99.9)
        ->and(CplMk::sisaBobotTersedia($this->mk->id, $this->cplBokA->id))->toBe(33.4)
        ->and(CplMk::sisaBobotTersedia($this->mk->id, $this->cplBokB->id))->toBe(33.4)
        ->and(CplMk::sisaBobotTersedia($this->mk->id, $cplBokC->id))->toBe(33.4);
});
