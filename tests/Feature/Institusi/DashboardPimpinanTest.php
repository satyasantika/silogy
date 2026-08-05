<?php

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Filament\Widgets\PimpinanCapaianTertinggiTable;
use App\Modules\Institusi\Filament\Widgets\PimpinanCplTertinggiChartWidget;
use App\Modules\Institusi\Filament\Widgets\PimpinanKpiWidget;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Services\DashboardPimpinanService;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Filament\Widgets\CplPerMkUnitTable;
use App\Modules\Kalkulasi\Filament\Widgets\CplUnitChartWidget;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;

uses(RefreshDatabase::class);

/**
 * Menulis hasil kalkulasi CPL apa adanya (tanpa memutar rantai kalkulator)
 * untuk satu penawaran MK: satu nilai akhir per mahasiswa.
 *
 * @param  list<float>  $nilaiPerMahasiswa
 */
function catatCapaianCplPimpinan(
    AcademicUnit $prodi,
    Kurikulum $kurikulum,
    string $kodeCpl,
    string $kodeMkUnit,
    string $namaMk,
    array $nilaiPerMahasiswa,
): void {
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $cpl = Cpl::query()->where('kode', $kodeCpl)->where('kurikulum_id', $kurikulum->id)->first()
        ?? Cpl::factory()->forKurikulum($kurikulum)->create(['kode' => $kodeCpl]);

    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id, 'nama' => $namaMk]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forKurikulum($kurikulum)->create([
        'kode' => $kodeMkUnit,
        'is_active' => true,
    ]);

    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);

    foreach ($nilaiPerMahasiswa as $nilai) {
        $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);
        $kmm = KelasMkMahasiswa::query()->create([
            'kelas_mk_id' => $kelas->id,
            'mahasiswa_id' => $mahasiswa->id,
        ]);

        HasilCplMk::query()->create([
            'cpl_id' => $cpl->id,
            'mk_unit_id' => $mkUnit->id,
            'kelas_mk_mahasiswa_id' => $kmm->id,
            'semester_id' => $semester->id,
            'nilai_akhir' => $nilai,
        ]);
    }
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    // Hierarki dari AcademicUnitSeeder: Universitas -> Fakultas -> Prodi
    // (prodi berinduk langsung ke fakultas).
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $this->universitas = AcademicUnit::query()->where('type', 'university')->firstOrFail();

    $this->kaprodi = User::query()->where('username', 'kaprodi')->firstOrFail();
    $this->dekan = User::query()->where('username', 'dekan')->firstOrFail();
    $this->rektor = User::query()->where('username', 'rektor')->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Dashboard Pimpinan',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('semua widget dashboard pimpinan terdaftar sebagai komponen livewire', function (string $widget) {
    $registry = app(ComponentRegistry::class);

    expect($registry->getClass($registry->getName($widget)))->toBe($widget);
})->with([
    PimpinanKpiWidget::class,
    PimpinanCplTertinggiChartWidget::class,
    PimpinanCapaianTertinggiTable::class,
]);

it('dashboard pimpinan menambahkan KPI kepemimpinan tanpa menghilangkan filter CPL generik dan Insight AI', function () {
    $this->actingAs($this->kaprodi);

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Ringkasan Kepemimpinan')
        ->assertSee('Capaian CPL Tertinggi (lintas kurikulum)', escape: false)
        ->assertSee('10 Capaian Mata Kuliah Tertinggi yang Ditawarkan')
        ->assertSee('Filter Dashboard CPL')
        ->assertSee('Capaian CPL per Unit');

    expect(PimpinanKpiWidget::canView())->toBeTrue()
        ->and(PimpinanCplTertinggiChartWidget::canView())->toBeTrue()
        ->and(PimpinanCapaianTertinggiTable::canView())->toBeTrue()
        ->and(CplUnitChartWidget::canView())->toBeTrue()
        ->and(CplPerMkUnitTable::canView())->toBeTrue();
});

it('KPI kepemimpinan prodi menghitung kurikulum dan unit dikelola pada penugasan langsung', function () {
    $this->actingAs($this->kaprodi);

    $service = app(DashboardPimpinanService::class);

    expect($service->jumlahKurikulum($this->kaprodi))->toBe(1)
        ->and($service->jumlahUnitDikelola($this->kaprodi))->toBe(1);

    Livewire::test(PimpinanKpiWidget::class)
        ->assertSuccessful()
        ->assertSee('Kurikulum Aktif')
        ->assertSee('Unit Dikelola');
});

it('KPI kepemimpinan fakultas menggulung data prodi di bawahnya', function () {
    $this->actingAs($this->dekan);

    $service = app(DashboardPimpinanService::class);

    // Fakultas + jurusan + prodi (keduanya berinduk langsung ke fakultas,
    // lihat AcademicUnitSeeder) = 3 unit; kurikulum tersimpan di prodi.
    expect($service->jumlahUnitDikelola($this->dekan))->toBe(3)
        ->and($service->jumlahKurikulum($this->dekan))->toBe(1);
});

it('KPI kepemimpinan universitas menggulung seluruh fakultas, jurusan, dan prodi di bawahnya', function () {
    $this->actingAs($this->rektor);

    $service = app(DashboardPimpinanService::class);

    // Universitas + fakultas + jurusan + prodi = 4 unit.
    expect($service->jumlahUnitDikelola($this->rektor))->toBe(4)
        ->and($service->jumlahKurikulum($this->rektor))->toBe(1);
});

it('grafik CPL tertinggi pimpinan memeringkat rerata capaian lintas kurikulum pada unit kepemimpinannya', function () {
    $this->actingAs($this->dekan);

    catatCapaianCplPimpinan($this->prodi, $this->kurikulum, 'CPL-SEDANG', 'KU21510001', 'Statistika', [70.0, 80.0]);
    catatCapaianCplPimpinan($this->prodi, $this->kurikulum, 'CPL-TERTINGGI', 'KU21510002', 'Aljabar', [92.0, 96.0]);
    catatCapaianCplPimpinan($this->prodi, $this->kurikulum, 'CPL-RENDAH', 'KU21510003', 'Kalkulus', [50.0]);

    $baris = app(DashboardPimpinanService::class)->cplTertinggi($this->dekan, 2);

    expect($baris)->toHaveCount(2)
        ->and($baris[0]['cpl_kode'])->toBe('CPL-TERTINGGI')
        ->and($baris[0]['rata_rata'])->toBe(94.0)
        ->and($baris[1]['cpl_kode'])->toBe('CPL-SEDANG');

    Livewire::test(PimpinanCplTertinggiChartWidget::class)
        ->assertSuccessful()
        ->assertSee('CPL-TERTINGGI', escape: false);
});

it('capaian unit di luar jangkauan kepemimpinan tidak masuk peringkat pimpinan', function () {
    $prodiLain = AcademicUnit::factory()->studyProgram($this->fakultas)->create([
        'nama' => 'S1 Prodi Lain Dashboard Pimpinan',
        'code' => 'S1-LAIN-PMP',
        'kode_pddikti' => '84399',
    ]);

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $prodiLain->id,
        'nama' => 'Kurikulum Prodi Lain',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    catatCapaianCplPimpinan($prodiLain, $kurikulumLain, 'CPL-LUAR', 'KU21550001', 'MK Prodi Lain', [99.0]);
    catatCapaianCplPimpinan($this->prodi, $this->kurikulum, 'CPL-DALAM', 'KU21550002', 'MK Prodi Sendiri', [60.0]);

    $this->actingAs($this->kaprodi);

    $service = app(DashboardPimpinanService::class);

    expect(collect($service->cplTertinggi($this->kaprodi))->pluck('cpl_kode'))
        ->toContain('CPL-DALAM')
        ->not->toContain('CPL-LUAR')
        ->and(collect($service->mkTertinggi($this->kaprodi))->pluck('mk_nama'))
        ->toContain('MK Prodi Sendiri')
        ->not->toContain('MK Prodi Lain');
});
