<?php

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalkulasi\Filament\Widgets\CplUnitChartWidget;
use App\Modules\Kalkulasi\Jobs\RecalkulasiCplJob;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kalkulasi\Services\DashboardCplDataService;
use App\Modules\Kalkulasi\Support\DashboardCplCache;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\Concerns\SetsUpKalkulasiFixtures;

uses(RefreshDatabase::class, SetsUpKalkulasiFixtures::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('shows cpl chart for kaprodi', function () {
    $kaprodi = User::where('username', 'kaprodi')->firstOrFail();
    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);

    $this->buatKurikulumAktif($prodi);

    HasilCplUnit::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'academic_unit_id' => $prodi->id,
        'semester_id' => $dasar['semester']->id,
        'rata_rata' => 82.5,
        'persentase_tercapai' => 100,
        'jumlah_mahasiswa' => 1,
    ]);

    $this->actingAs($kaprodi);

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Capaian CPL per Unit')
        ->assertSee('Filter Dashboard CPL');

    Livewire::test(CplUnitChartWidget::class, [
        'pageFilters' => [
            'academic_unit_id' => $prodi->id,
            'semester_id' => $dasar['semester']->id,
            'cpl_id' => $dasar['cpl']->id,
        ],
    ])
        ->assertSuccessful();
});

it('filters data per academic unit', function () {
    $kaprodi = User::where('username', 'kaprodi')->firstOrFail();
    $dasar = $this->createKelasPenilaianDasar('IF-A');
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);

    $jur = $prodi->parent;
    $prodi2 = AcademicUnit::factory()->studyProgram($jur)->create([
        'nama' => 'S1 Sistem Informasi',
        'code' => 'S1-SI',
        'kode_pddikti' => '57201',
    ]);

    $cpl2 = Cpl::factory()->forAcademicUnit($prodi2)->create(['kode' => 'CPL-B']);

    HasilCplUnit::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'academic_unit_id' => $prodi->id,
        'semester_id' => $dasar['semester']->id,
        'rata_rata' => 70,
        'persentase_tercapai' => 80,
        'jumlah_mahasiswa' => 5,
    ]);

    HasilCplUnit::query()->create([
        'cpl_id' => $cpl2->id,
        'academic_unit_id' => $prodi2->id,
        'semester_id' => $dasar['semester']->id,
        'rata_rata' => 90,
        'persentase_tercapai' => 95,
        'jumlah_mahasiswa' => 8,
    ]);

    $service = app(DashboardCplDataService::class);

    $dataProdi1 = $service->chartData($prodi->id, $dasar['semester']->id);
    $dataProdi2 = $service->chartData($prodi2->id, $dasar['semester']->id);

    expect($dataProdi1['rows'][0]['rata_rata'])->toBe(70.0)
        ->and($dataProdi2['rows'][0]['rata_rata'])->toBe(90.0);

    $this->actingAs($kaprodi);

    Livewire::test(Dashboard::class)
        ->set('filters.academic_unit_id', $prodi2->id)
        ->assertSet('filters.academic_unit_id', $prodi2->id);
});

it('caches dashboard query', function () {
    Cache::flush();

    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);

    HasilCplUnit::query()->create([
        'cpl_id' => $dasar['cpl']->id,
        'academic_unit_id' => $prodi->id,
        'semester_id' => $dasar['semester']->id,
        'rata_rata' => 75,
        'persentase_tercapai' => 100,
        'jumlah_mahasiswa' => 1,
    ]);

    $service = app(DashboardCplDataService::class);
    $key = DashboardCplCache::chartKey($prodi->id, $dasar['semester']->id);

    expect(Cache::has($key))->toBeFalse();

    $first = $service->chartData($prodi->id, $dasar['semester']->id);

    expect(Cache::has($key))->toBeTrue()
        ->and($first['rows'][0]['rata_rata'])->toBe(75.0);

    HasilCplUnit::query()
        ->where('academic_unit_id', $prodi->id)
        ->update(['rata_rata' => 99]);

    $cached = $service->chartData($prodi->id, $dasar['semester']->id);

    expect($cached['rows'][0]['rata_rata'])->toBe(75.0);

    DashboardCplCache::invalidate($prodi->id, $dasar['semester']->id);

    expect(Cache::has($key))->toBeFalse();

    $fresh = $service->chartData($prodi->id, $dasar['semester']->id);

    expect($fresh['rows'][0]['rata_rata'])->toBe(99.0);
});

it('invalidates dashboard cache after recalkulasi job', function () {
    Cache::flush();

    $dasar = $this->createKelasPenilaianDasar();
    $prodi = AcademicUnit::query()->findOrFail($dasar['mk']->academic_unit_id);

    $this->buatKurikulumAktif($prodi);

    NilaiMahasiswa::withoutEvents(function () use ($dasar): void {
        $skp = $this->buatKomponenSkp(
            $dasar['kelas'],
            $dasar['subcpmk'],
            $dasar['evaluasi'],
            'UTS',
            100,
            100,
        );
        $this->isiNilai($skp, $dasar['kmm'], 88);
    });

    RecalkulasiCplJob::dispatchSync(
        $dasar['kelas']->id,
        $prodi->id,
        $dasar['semester']->id,
    );

    $key = DashboardCplCache::chartKey($prodi->id, $dasar['semester']->id);

    expect(Cache::has($key))->toBeFalse()
        ->and(HasilCplUnit::query()->where('academic_unit_id', $prodi->id)->exists())->toBeTrue();
});
