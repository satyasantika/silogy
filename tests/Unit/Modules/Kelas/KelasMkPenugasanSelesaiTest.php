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
});

it('bobot komponen yang genuinely berjumlah 100.00 tetap dianggap selesai walau rawan presisi float', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create();
    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
    ]);

    $cpmk = Cpmk::factory()->forMk($mk)->create();
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create();

    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();

    // 9 komponen 10.10% + satu 9.10% -- secara desimal genap 100.00, tapi
    // array_sum()/Collection::sum() atas nilai decimal:2 ini menghasilkan
    // double 99.99999999999998... di PHP, bukan 100.0 persis (diverifikasi
    // langsung: array_sum(["10.10" x9, "9.10"]) !== 100.0).
    $bobotList = array_merge(array_fill(0, 9, 10.10), [9.10]);

    foreach ($bobotList as $i => $bobot) {
        $komponen = KomponenPenilaian::query()->create([
            'mk_id' => $mk->id,
            'semester_id' => $this->semester->id,
            'evaluasi_id' => $evaluasi->id,
            'kode' => 'K'.$i,
            'nama' => 'Komponen '.$i,
            'bobot' => $bobot,
        ]);

        SubcpmkKomponenPenilaian::query()->create([
            'subcpmk_id' => $subcpmk->id,
            'komponen_penilaian_id' => $komponen->id,
            'bobot' => 100,
        ]);
    }

    // Sengaja get() dulu baru sum() di Collection (bukan Builder::sum(),
    // yang mengagregasi via SQL dan presisi karena DECIMAL di MySQL) —
    // meniru persis jalur kode penugasanSelesai(), yang mengambil model
    // lebih dulu lalu menjumlah atribut decimal:2 (string) lewat PHP.
    $sumMentah = (float) KomponenPenilaian::query()
        ->where('mk_id', $mk->id)
        ->where('semester_id', $this->semester->id)
        ->get()
        ->sum('bobot');

    expect($sumMentah)->not->toBe(100.0)
        ->and($kelas->penugasanSelesai())->toBeTrue();
});

it('bobot komponen yang genuinely kurang dari 100 tetap dianggap belum selesai', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create();
    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
    ]);

    $cpmk = Cpmk::factory()->forMk($mk)->create();
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create();

    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();

    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 60,
    ]);

    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);

    expect($kelas->penugasanSelesai())->toBeFalse();
});
