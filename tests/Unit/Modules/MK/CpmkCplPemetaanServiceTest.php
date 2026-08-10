<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Services\CpmkCplPemetaanService;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
});

it('validasi kode cpl menolak bila cpl mk belum ada', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-X']);

    $hasil = CpmkCplPemetaanService::validasiKodeCplUntukMk('CPL-X', $mk);

    expect($hasil['valid'])->toBeFalse()
        ->and($hasil['keterangan'])->toContain('CPL ↔ MK');
});

it('validasi kode cpl memilih generasi kurikulum yang benar, bukan cpl generasi lama dengan kode sama', function () {
    $kurikulumLama = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Lama',
        'tahun' => 2024,
        'is_active' => false,
    ]);
    $kurikulumBaru = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Baru',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    // CPL06 generasi LAMA — dibuat lebih dulu, kode sama, TIDAK pernah dipetakan ke MK manapun.
    Cpl::factory()->forKurikulum($kurikulumLama)->create(['kode' => 'CPL06']);

    // CPL06 generasi BARU — inilah yang sungguh dipetakan ke MK target.
    $cplBaru = Cpl::factory()->forKurikulum($kurikulumBaru)->create(['kode' => 'CPL06']);
    $mk = Mk::factory()->forKurikulum($kurikulumBaru)->create();
    $bok = Bok::factory()->forKurikulum($kurikulumBaru)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cplBaru->id, 'bok_id' => $bok->id]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);

    $hasil = CpmkCplPemetaanService::validasiKodeCplUntukMk('CPL06', $mk);

    expect($hasil['valid'])->toBeTrue();
});

it('petakan cpmk ke cpl membuat baris mk cpmk', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-MAP']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create([
        'mk_id' => $mk->id,
        'kode' => 'CPMK-MAP',
        'deskripsi' => 'Uji pemetaan.',
    ]);

    CpmkCplPemetaanService::petakanCpmkKeCpl($cpmk, 'CPL-MAP');

    expect(MkCpmk::query()
        ->where('cpmk_id', $cpmk->id)
        ->where('cpl_mk_id', $cplMk->id)
        ->exists())->toBeTrue();
});
