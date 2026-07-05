<?php

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Services\AdaptasiMkMassalService;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);

    $this->service = app(AdaptasiMkMassalService::class);
    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $this->kurikulumUniv = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Kurikulum Univ Uji',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->kurikulumFak = Kurikulum::query()->create([
        'academic_unit_id' => $this->fakultas->id,
        'nama' => 'Kurikulum Fak Uji',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Prodi Uji',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('mengambil mk aktif dari kurikulum universitas fakultas dan prodi sekaligus', function () {
    Mk::factory()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Pendidikan Pancasila',
        'is_active' => true,
    ]);

    Mk::factory()->create([
        'academic_unit_id' => $this->fakultas->id,
        'nama' => 'Metodologi Penelitian',
        'is_active' => true,
    ]);

    Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kalkulus',
        'is_active' => true,
    ]);

    $rows = $this->service->resolveBaris([
        'adaptasi_unit_id' => $this->prodi->id,
        'kurikulum_univ_id' => $this->kurikulumUniv->id,
        'kurikulum_fakultas_id' => $this->kurikulumFak->id,
        'kurikulum_prodi_id' => $this->kurikulumProdi->id,
    ]);

    expect($rows)->toHaveCount(3)
        ->and(collect($rows)->pluck('status')->unique()->all())->toBe(['baru'])
        ->and(collect($rows)->pluck('nama')->sort()->values()->all())->toBe([
            'Kalkulus',
            'Metodologi Penelitian',
            'Pendidikan Pancasila',
        ]);
});

it('menandai duplikat bila mk sudah ditawarkan pada prodi', function () {
    $mkUniv = Mk::factory()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Pendidikan Pancasila',
    ]);

    MkUnit::factory()->forMk($mkUniv)->forAcademicUnit($this->prodi)->create(['kode' => 'PP-1']);

    $rows = $this->service->resolveBaris([
        'adaptasi_unit_id' => $this->prodi->id,
        'kurikulum_univ_id' => $this->kurikulumUniv->id,
    ]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['status'])->toBe('duplikat');
});

it('mode lewati tidak membuat penawaran duplikat', function () {
    $mkUniv = Mk::factory()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Pendidikan Pancasila',
    ]);

    MkUnit::factory()->forMk($mkUniv)->forAcademicUnit($this->prodi)->create([
        'kode' => 'PP-1',
        'is_active' => false,
    ]);

    $context = [
        'adaptasi_unit_id' => $this->prodi->id,
        'kurikulum_univ_id' => $this->kurikulumUniv->id,
    ];

    $rows = $this->service->resolveBaris($context);
    $hasil = $this->service->jalankan($rows, 'lewati', $context);

    expect($hasil['dibuat'])->toBe(0)
        ->and($hasil['dilewati'])->toBe(1)
        ->and(MkUnit::query()->where('academic_unit_id', $this->prodi->id)->count())->toBe(1);
});

it('mode timpa mengaktifkan kembali penawaran duplikat', function () {
    $mkUniv = Mk::factory()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Pendidikan Pancasila',
    ]);

    $penawaran = MkUnit::factory()->forMk($mkUniv)->forAcademicUnit($this->prodi)->create([
        'kode' => 'PP-1',
        'is_active' => false,
    ]);

    $context = [
        'adaptasi_unit_id' => $this->prodi->id,
        'kurikulum_univ_id' => $this->kurikulumUniv->id,
    ];

    $rows = $this->service->resolveBaris($context);
    $hasil = $this->service->jalankan($rows, 'timpa', $context);

    expect($hasil['diperbarui'])->toBe(1)
        ->and($penawaran->fresh()->is_active)->toBeTrue();
});

it('membuat penawaran mk dengan kode otomatis dari nama', function () {
    $mkUniv = Mk::factory()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Pendidikan Pancasila',
    ]);

    $context = [
        'adaptasi_unit_id' => $this->prodi->id,
        'kurikulum_univ_id' => $this->kurikulumUniv->id,
    ];

    $rows = $this->service->resolveBaris($context);
    $hasil = $this->service->jalankan($rows, 'lewati', $context);

    $penawaran = MkUnit::query()->where('mk_id', $mkUniv->id)->where('academic_unit_id', $this->prodi->id)->first();

    expect($hasil['dibuat'])->toBe(1)
        ->and($penawaran)->not->toBeNull()
        ->and($penawaran->kode)->not->toBe('')
        ->and($penawaran->semester_ke)->toBeNull();
});

it('menolak adaptasi bila tidak ada kurikulum sumber dipilih', function () {
    $rows = $this->service->resolveBaris([
        'adaptasi_unit_id' => $this->prodi->id,
    ]);

    expect($rows[0]['status'])->toBe('invalid')
        ->and($rows[0]['keterangan'])->toContain('minimal satu kurikulum');
});

it('menolak kurikulum sumber yang tidak sesuai unit penawaran', function () {
    $fakultasLain = AcademicUnit::factory()->faculty($this->univ)->create([
        'nama' => 'Fakultas Lain',
        'code' => 'FKL',
    ]);

    $prodiLain = AcademicUnit::factory()->studyProgram($fakultasLain)->create([
        'nama' => 'Prodi Lain',
        'code' => 'PLN',
    ]);

    $rows = $this->service->resolveBaris([
        'adaptasi_unit_id' => $prodiLain->id,
        'kurikulum_fakultas_id' => $this->kurikulumFak->id,
    ]);

    expect($rows[0]['status'])->toBe('invalid')
        ->and($rows[0]['keterangan'])->toContain('Fakultas');
});
