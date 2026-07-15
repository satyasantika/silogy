<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplKodeOverride;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Support\CplBokAdaptasiScope;
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

    $this->mkUniv = Mk::factory()->forAcademicUnit($this->univ)->create();
    $this->cplUniv = Cpl::factory()->forAcademicUnit($this->univ)->create();
    $this->bokUniv = Bok::factory()->forAcademicUnit($this->univ)->create();
    $this->cplBokUniv = CplBok::query()->create([
        'cpl_id' => $this->cplUniv->id,
        'bok_id' => $this->bokUniv->id,
    ]);
    CplMk::query()->create([
        'cpl_bok_id' => $this->cplBokUniv->id,
        'mk_id' => $this->mkUniv->id,
        'bobot' => 60,
    ]);
});

it('mk yang belum diadaptasi tidak menghasilkan cpl/bok teradaptasi', function () {
    expect(CplBokAdaptasiScope::adaptedMkIds($this->prodi->id))->toBeEmpty()
        ->and(CplBokAdaptasiScope::adaptedCplIds($this->prodi->id))->toBeEmpty()
        ->and(CplBokAdaptasiScope::adaptedBokIds($this->prodi->id))->toBeEmpty();
});

it('cpl/bok universitas yang tidak terhubung mk manapun tidak ikut muncul', function () {
    MkUnit::factory()->forAcademicUnit($this->prodi)->forMk($this->mkUniv)->create(['is_active' => true]);

    // CPL/BoK universitas lain yang TIDAK dipetakan ke mkUniv sama sekali —
    // harus tetap tersembunyi walau mkUniv sudah diadaptasi prodi.
    $mkLainUniv = Mk::factory()->forAcademicUnit($this->univ)->create();
    $cplLain = Cpl::factory()->forAcademicUnit($this->univ)->create();
    $bokLain = Bok::factory()->forAcademicUnit($this->univ)->create();
    $cplBokLain = CplBok::query()->create(['cpl_id' => $cplLain->id, 'bok_id' => $bokLain->id]);
    CplMk::query()->create(['cpl_bok_id' => $cplBokLain->id, 'mk_id' => $mkLainUniv->id, 'bobot' => 50]);

    expect(CplBokAdaptasiScope::adaptedCplIds($this->prodi->id))
        ->toContain($this->cplUniv->id)
        ->not->toContain($cplLain->id);
});

it('mengadaptasi mk universitas menyingkap cpl dan bok terkait', function () {
    MkUnit::factory()->forAcademicUnit($this->prodi)->forMk($this->mkUniv)->create(['is_active' => true]);

    expect(CplBokAdaptasiScope::adaptedMkIds($this->prodi->id)->all())->toBe([$this->mkUniv->id])
        ->and(CplBokAdaptasiScope::adaptedCplBokIds($this->prodi->id)->all())->toBe([$this->cplBokUniv->id])
        ->and(CplBokAdaptasiScope::adaptedCplIds($this->prodi->id)->all())->toBe([$this->cplUniv->id])
        ->and(CplBokAdaptasiScope::adaptedBokIds($this->prodi->id)->all())->toBe([$this->bokUniv->id]);

    $visibleCpl = CplBokAdaptasiScope::scopeVisibleCpl(Cpl::query(), $this->prodi->id)->pluck('id');
    expect($visibleCpl)->toContain($this->cplUniv->id);
});

it('mk_unit tidak aktif tidak menyingkap cpl/bok', function () {
    MkUnit::factory()->forAcademicUnit($this->prodi)->forMk($this->mkUniv)->create(['is_active' => false]);

    expect(CplBokAdaptasiScope::adaptedCplIds($this->prodi->id))->toBeEmpty();
});

it('canToggleCplBok true bila salah satu sisi milik unit yang melihat', function () {
    $cplProdi = Cpl::factory()->forAcademicUnit($this->prodi)->create();

    expect(CplBokAdaptasiScope::canToggleCplBok($cplProdi, $this->bokUniv, $this->prodi->id))->toBeTrue()
        ->and(CplBokAdaptasiScope::canToggleCplBok($this->cplUniv, $this->bokUniv, $this->prodi->id))->toBeFalse();
});

it('canEditCplMkCell true bila mk atau cplBok milik unit yang melihat', function () {
    MkUnit::factory()->forAcademicUnit($this->prodi)->forMk($this->mkUniv)->create(['is_active' => true]);

    $mkProdi = Mk::factory()->forAcademicUnit($this->prodi)->create();

    // MK milik prodi sendiri x CplBok universitas -> boleh diedit (kasus eksplisit diminta).
    expect(CplBokAdaptasiScope::canEditCplMkCell($mkProdi, $this->cplBokUniv, $this->prodi->id))->toBeTrue()
        // MK universitas (walau sudah diadaptasi) x CplBok universitas -> murni asing, read-only.
        ->and(CplBokAdaptasiScope::canEditCplMkCell($this->mkUniv, $this->cplBokUniv, $this->prodi->id))->toBeFalse();
});

it('displayKodeMapCpl memakai kode asli kecuali ada override milik unit', function () {
    $map = CplBokAdaptasiScope::displayKodeMapCpl(collect([$this->cplUniv]), $this->prodi->id);
    expect($map[$this->cplUniv->id])->toBe($this->cplUniv->kode);

    CplKodeOverride::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'cpl_id' => $this->cplUniv->id,
        'kode' => 'CPL-PRODI-1',
    ]);

    $mapSetelahOverride = CplBokAdaptasiScope::displayKodeMapCpl(collect([$this->cplUniv->fresh()]), $this->prodi->id);
    expect($mapSetelahOverride[$this->cplUniv->id])->toBe('CPL-PRODI-1');

    // Override milik unit lain tidak boleh terikut.
    $mapUnitLain = CplBokAdaptasiScope::displayKodeMapCpl(collect([$this->cplUniv->fresh()]), $this->univ->id);
    expect($mapUnitLain[$this->cplUniv->id])->toBe($this->cplUniv->kode);
});

it('isVisiblePair dan isVisibleMkCplBokCell menolak id di luar semesta terlihat', function () {
    MkUnit::factory()->forAcademicUnit($this->prodi)->forMk($this->mkUniv)->create(['is_active' => true]);

    $bokLain = Bok::factory()->forAcademicUnit($this->univ)->create();

    expect(CplBokAdaptasiScope::isVisiblePair($this->cplUniv->id, $this->bokUniv->id, $this->prodi->id))->toBeTrue()
        ->and(CplBokAdaptasiScope::isVisiblePair($this->cplUniv->id, $bokLain->id, $this->prodi->id))->toBeFalse()
        ->and(CplBokAdaptasiScope::isVisibleMkCplBokCell($this->mkUniv->id, $this->cplBokUniv->id, $this->prodi->id))->toBeTrue();

    $mkLain = Mk::factory()->forAcademicUnit($this->univ)->create();
    expect(CplBokAdaptasiScope::isVisibleMkCplBokCell($mkLain->id, $this->cplBokUniv->id, $this->prodi->id))->toBeFalse();
});
