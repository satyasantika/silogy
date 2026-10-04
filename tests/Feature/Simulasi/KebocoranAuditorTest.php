<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\Support\SimulasiAkademikBuilder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Auditor Mutu dibypass dari pembatasan unit (hasRole di puluhan tempat), jadi
 * akun Auditor di sandbox dulu bisa membaca data NYATA seluruh institusi.
 * Sekarang pagar ranah menutupnya di lapisan model. Tes ini memindai SETIAP
 * resource panel, bukan daftar tulis-tangan, supaya resource baru yang bocor
 * langsung ketahuan.
 */

/**
 * Tabel yang memang dipakai bersama data inti dan sandbox (dipinjam, tidak
 * pernah dimiliki sandbox) — lihat PencatatArtefak::MODEL_DIPINJAM.
 */
const TABEL_BERSAMA = ['semesters', 'roles', 'permissions', 'evaluasi'];

beforeEach(function () {
    (new AcademicUnitSeeder)->run();
    (new RolePermissionSeeder)->run();
    (new SemesterSeeder)->run();
    (new EvaluasiSeeder)->run();
});

/** Data "nyata": rantai OBE penuh di prodi nyata, dibangun tanpa ranah sandbox. */
function bangunDataNyata(): array
{
    $prodi = AcademicUnit::query()->where('type', 'study_program')->where('code', 'not like', 'SIM-%')->firstOrFail();
    $buat = fn (string $n) => User::factory()->create(['username' => $n, 'email' => $n.'@nyata.test']);

    (new SimulasiAkademikBuilder(
        Semester::query()->where('status_aktif', true)->firstOrFail(),
        $buat('nyata-a'), $buat('nyata-b'), $buat('nyata-c'),
    ))->seedProdi($prodi);

    $idNyata = [];

    foreach (Schema::getTableListing() as $tabel) {
        $tabel = str_contains($tabel, '.') ? substr((string) strrchr($tabel, '.'), 1) : $tabel;

        if (Schema::hasColumn($tabel, 'id')) {
            $idNyata[$tabel] = DB::table($tabel)->pluck('id')->map(fn ($i) => (string) $i)->all();
        }
    }

    return $idNyata;
}

it('Auditor sandbox tidak melihat satu baris data nyata pada resource mana pun', function (string $kunci) {
    $idNyata = bangunDataNyata();
    $jalan = app(SimulasiService::class)->buat()->jalan;

    $akun = Ranah::sebagai($jalan->id, fn () => User::query()
        ->where('username', AkunSimulasi::username($kunci, $jalan->kode()))->firstOrFail());

    $this->actingAs($akun);
    ActiveRole::set('Auditor Mutu');
    AcademicUnitTerpilih::set(AcademicUnitTerpilih::scopedUnitIdsForRole($akun, 'Auditor Mutu')->first());

    $bocor = [];
    $diperiksa = 0;

    foreach (Filament::getPanel('admin')->getResources() as $resource) {
        $model = new ($resource::getModel());
        $tabel = $model->getTable();

        if (in_array($tabel, TABEL_BERSAMA, true)) {
            continue;
        }

        $ids = $resource::getEloquentQuery()->pluck($model->getQualifiedKeyName())->map(fn ($i) => (string) $i)->all();
        $diperiksa++;

        $irisan = array_intersect($ids, $idNyata[$tabel] ?? []);

        if ($irisan !== []) {
            $bocor[] = class_basename($resource).' ('.count($irisan).' baris nyata)';
        }
    }

    expect($diperiksa)->toBeGreaterThan(15)
        ->and($bocor)->toBe([], 'resource bocor ke data nyata: '.implode(', ', $bocor));
})->with(['sim-auditor']);

it('Auditor sandbox tidak melihat data sandbox lain', function () {
    $simulasi = app(SimulasiService::class);
    $a = $simulasi->buat()->jalan;
    $b = $simulasi->buat()->jalan;

    $akun = Ranah::sebagai($a->id, fn () => User::query()
        ->where('username', AkunSimulasi::username('sim-auditor', $a->kode()))->firstOrFail());

    $this->actingAs($akun);
    ActiveRole::set('Auditor Mutu');

    $idB = Ranah::sebagai($b->id, fn () => Kurikulum::query()->pluck('id')->all());

    expect($idB)->not->toBeEmpty()
        ->and(Kurikulum::query()->whereIn('id', $idB)->count())->toBe(0)
        ->and(Cpmk::query()->whereIn('mk_id', Ranah::sebagai($b->id, fn () => Mk::query()->pluck('id')->all()))->count())->toBe(0);
});

it('Auditor nyata tetap melihat seluruh data nyata', function () {
    bangunDataNyata();
    app(SimulasiService::class)->buat();

    $auditor = User::query()->where('username', 'auditor')->firstOrFail();
    $this->actingAs($auditor);
    ActiveRole::set('Auditor Mutu');

    expect(Cpmk::query()->count())->toBeGreaterThan(0)
        ->and(Subcpmk::query()->count())->toBeGreaterThan(0)
        ->and(KomponenPenilaian::query()->count())->toBeGreaterThan(0);
});
