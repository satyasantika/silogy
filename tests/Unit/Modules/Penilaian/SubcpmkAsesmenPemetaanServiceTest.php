<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Services\SubcpmkAsesmenPemetaanService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create();
    $this->kelas = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
    ]);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $this->mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $this->mk->id, 'kode' => 'CPMK-01', 'deskripsi' => 'Uji']);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);

    $this->sub1 = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id,
        'semester_id' => $this->semester->id,
        'kode' => 'SubCPMK01.1',
        'deskripsi' => 'Sub 1',
    ]);
    $this->sub2 = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id,
        'semester_id' => $this->semester->id,
        'kode' => 'SubCPMK01.2',
        'deskripsi' => 'Sub 2',
    ]);

    $this->komponen = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => Evaluasi::query()->where('kode', 'quiz')->value('id'),
        'kode' => 'Asesmen01',
        'nama' => 'Kuis',
        'bobot' => 8,
    ]);
});

it('membagi bobot pivot subcpmk merata setelah pemetaan', function () {
    SubcpmkAsesmenPemetaanService::petakanSubcpmk($this->komponen, $this->sub1);
    SubcpmkAsesmenPemetaanService::petakanSubcpmk($this->komponen, $this->sub2);

    $bobots = SubcpmkKomponenPenilaian::query()
        ->where('komponen_penilaian_id', $this->komponen->id)
        ->pluck('bobot')
        ->all();

    expect($bobots)->toHaveCount(2)
        ->and($bobots[0])->toBe(50.0)
        ->and($bobots[1])->toBe(50.0)
        ->and(array_sum($bobots))->toBe(100.0);
});

it('mencari subcpmk berdasarkan kode pada mk dan semester', function () {
    $sub = SubcpmkAsesmenPemetaanService::cariSubcpmkUntukMk('SubCPMK01.1', $this->mk->id, $this->semester->id);

    expect($sub?->id)->toBe($this->sub1->id);
});

it('bobot subcpmk dihitung ulang otomatis setiap interaksi dengan komponen penilaian', function () {
    SubcpmkAsesmenPemetaanService::petakanSubcpmk($this->komponen, $this->sub1);
    SubcpmkAsesmenPemetaanService::petakanSubcpmk($this->komponen, $this->sub2);

    // Komponen bobot 8%, dipetakan merata ke 2 Sub-CPMK (50% masing-masing) => 8 * 50% = 4.
    expect((float) $this->sub1->fresh()->bobot)->toBe(4.0)
        ->and((float) $this->sub2->fresh()->bobot)->toBe(4.0);
});

it('bobot subcpmk kembali ke 0 setelah satu-satunya interaksinya dihapus', function () {
    SubcpmkAsesmenPemetaanService::petakanSubcpmk($this->komponen, $this->sub1);

    expect((float) $this->sub1->fresh()->bobot)->toBe(8.0);

    SubcpmkKomponenPenilaian::query()
        ->where('subcpmk_id', $this->sub1->id)
        ->where('komponen_penilaian_id', $this->komponen->id)
        ->firstOrFail()
        ->delete();

    expect((float) $this->sub1->fresh()->bobot)->toBe(0.0);
});
