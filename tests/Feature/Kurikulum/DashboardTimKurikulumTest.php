<?php

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\WelcomeWidget;
use App\Models\User;
use App\Modules\AI\Filament\Widgets\AiInsightWidget;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Filament\Widgets\CplPerMkUnitTable;
use App\Modules\Kalkulasi\Filament\Widgets\CplUnitChartWidget;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Kurikulum\Filament\Widgets\CplTertinggiChartWidget;
use App\Modules\Kurikulum\Filament\Widgets\KurikulumKpiWidget;
use App\Modules\Kurikulum\Filament\Widgets\KurikulumTerpilihWidget;
use App\Modules\Kurikulum\Filament\Widgets\MkCapaianTertinggiTable;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Services\DashboardTimKurikulumService;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Filament\Widgets\KoordinatorMkAksesWidget;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Filament\Widgets\RekapMkDosenWidget;
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
function catatCapaianCpl(
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

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->kaprodi = User::query()->where('username', 'kaprodi')->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Dashboard Aktif',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->kurikulumLama = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Dashboard Lama',
        'tahun' => 2021,
        'is_active' => false,
    ]);
});

it('semua widget dashboard terdaftar sebagai komponen livewire', function (string $widget) {
    // Widget yang tidak terdaftar di panel masih bisa dirender sekali, tapi
    // request /livewire/update berikutnya (polling, wire:model.live, aksi)
    // gagal dengan ComponentNotFoundException karena aliasnya tidak dikenal.
    $registry = app(ComponentRegistry::class);

    expect($registry->getClass($registry->getName($widget)))->toBe($widget);
})->with([
    WelcomeWidget::class,
    AiInsightWidget::class,
    CplUnitChartWidget::class,
    CplPerMkUnitTable::class,
    KurikulumTerpilihWidget::class,
    KurikulumKpiWidget::class,
    CplTertinggiChartWidget::class,
    MkCapaianTertinggiTable::class,
    KoordinatorMkAksesWidget::class,
    RekapMkDosenWidget::class,
]);

it('dashboard tim kurikulum tidak lagi memuat kartu kurikulum, filter CPL, capaian per unit, dan drill-down MK', function () {
    $this->actingAs($this->timkur);

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertDontSee('Kurikulum yang dikerjakan')
        ->assertDontSee('Filter Dashboard CPL')
        ->assertDontSee('Capaian CPL per Unit')
        ->assertDontSee('Drill-down Capaian per MK');

    expect(CplUnitChartWidget::canView())->toBeFalse()
        ->and(CplPerMkUnitTable::canView())->toBeFalse()
        ->and(KurikulumTerpilihWidget::canView())->toBeTrue();
});

it('dashboard tim kurikulum memuat KPI kurikulum, grafik CPL tertinggi, dan peringkat capaian MK', function () {
    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Banyaknya Kurikulum')
        ->assertSee('Kurikulum Dikerjakan')
        ->assertSee('Capaian CPL Tertinggi (lintas kurikulum)', escape: false)
        ->assertSee('10 Capaian Mata Kuliah Tertinggi yang Ditawarkan');

    expect(KurikulumKpiWidget::canView())->toBeTrue()
        ->and(CplTertinggiChartWidget::canView())->toBeTrue()
        ->and(MkCapaianTertinggiTable::canView())->toBeTrue();
});

it('pimpinan tetap mendapat filter dashboard CPL beserta widget capaian per unit', function () {
    $this->actingAs($this->kaprodi);

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Filter Dashboard CPL')
        ->assertSee('Capaian CPL per Unit');

    expect(CplUnitChartWidget::canView())->toBeTrue()
        ->and(KurikulumKpiWidget::canView())->toBeFalse()
        ->and(CplTertinggiChartWidget::canView())->toBeFalse()
        ->and(MkCapaianTertinggiTable::canView())->toBeFalse();
});

it('KPI menghitung kurikulum unit kerja dan menautkan daftar kurikulum serta profil lulusan', function () {
    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    expect(app(DashboardTimKurikulumService::class)->jumlahKurikulum($this->timkur))->toBe(2);

    Livewire::test(KurikulumKpiWidget::class)
        ->assertSee('Banyaknya Kurikulum')
        ->assertSee('Kurikulum Dashboard Aktif')
        ->assertSeeHtml(KurikulumResource::getUrl('index'))
        ->assertSeeHtml(ProfilLulusanResource::getUrl('index'));
});

it('KPI menandai kondisi belum ada kurikulum sama sekali', function () {
    Kurikulum::query()->delete();

    $this->actingAs($this->timkur);

    expect(app(DashboardTimKurikulumService::class)->jumlahKurikulum($this->timkur))->toBe(0);

    Livewire::test(KurikulumKpiWidget::class)
        ->assertSee('Belum dipilih')
        ->assertSee('Belum ada kurikulum');
});

it('grafik CPL tertinggi memeringkat rerata capaian lintas kurikulum', function () {
    $this->actingAs($this->timkur);
    KurikulumTerpilih::set($this->kurikulum->id);

    catatCapaianCpl($this->prodi, $this->kurikulum, 'CPL-SEDANG', 'KU21510001', 'Statistika', [70.0, 80.0]);
    catatCapaianCpl($this->prodi, $this->kurikulumLama, 'CPL-TERTINGGI', 'KU21510002', 'Aljabar', [92.0, 96.0]);
    catatCapaianCpl($this->prodi, $this->kurikulum, 'CPL-RENDAH', 'KU21510003', 'Kalkulus', [50.0]);

    $baris = app(DashboardTimKurikulumService::class)->cplTertinggi($this->timkur, 2);

    expect($baris)->toHaveCount(2)
        ->and($baris[0]['cpl_kode'])->toBe('CPL-TERTINGGI')
        ->and($baris[0]['kurikulum_nama'])->toBe('Kurikulum Dashboard Lama')
        ->and($baris[0]['rata_rata'])->toBe(94.0)
        ->and($baris[0]['jumlah_mahasiswa'])->toBe(2)
        ->and($baris[1]['cpl_kode'])->toBe('CPL-SEDANG')
        ->and($baris[1]['rata_rata'])->toBe(75.0);

    Livewire::test(CplTertinggiChartWidget::class)
        ->assertSuccessful()
        ->assertSee('CPL-TERTINGGI', escape: false);
});

it('peringkat capaian mata kuliah dibatasi sepuluh penawaran teratas dan terurut menurun', function () {
    $this->actingAs($this->timkur);

    foreach (range(1, 11) as $nomor) {
        catatCapaianCpl(
            $this->prodi,
            $this->kurikulum,
            'CPL-MK-'.$nomor,
            sprintf('KU2152%04d', $nomor),
            'Mata Kuliah '.$nomor,
            [50.0 + $nomor],
        );
    }

    $baris = app(DashboardTimKurikulumService::class)->mkTertinggi($this->timkur, MkCapaianTertinggiTable::JUMLAH_MK);

    expect($baris)->toHaveCount(10)
        ->and($baris[0]['mk_nama'])->toBe('Mata Kuliah 11')
        ->and($baris[0]['rata_rata'])->toBe(61.0)
        ->and($baris[0]['jumlah_mahasiswa'])->toBe(1)
        ->and($baris[9]['mk_nama'])->toBe('Mata Kuliah 2')
        ->and(collect($baris)->pluck('mk_nama'))->not->toContain('Mata Kuliah 1');

    Livewire::test(MkCapaianTertinggiTable::class)
        ->assertSuccessful()
        ->assertSee('Mata Kuliah 11');
});

it('capaian unit lain tidak masuk peringkat tim kurikulum prodi', function () {
    $prodiLain = AcademicUnit::factory()->studyProgram($this->prodi->parent)->create([
        'nama' => 'S1 Prodi Lain Dashboard',
        'code' => 'S1-LAIN-DSB',
        'kode_pddikti' => '84299',
    ]);

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $prodiLain->id,
        'nama' => 'Kurikulum Prodi Lain',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    catatCapaianCpl($prodiLain, $kurikulumLain, 'CPL-LUAR', 'KU21530001', 'MK Prodi Lain', [99.0]);
    catatCapaianCpl($this->prodi, $this->kurikulum, 'CPL-DALAM', 'KU21530002', 'MK Prodi Sendiri', [60.0]);

    $this->actingAs($this->timkur);

    $service = app(DashboardTimKurikulumService::class);

    expect(collect($service->cplTertinggi($this->timkur))->pluck('cpl_kode'))
        ->toContain('CPL-DALAM')
        ->not->toContain('CPL-LUAR')
        ->and(collect($service->mkTertinggi($this->timkur))->pluck('mk_nama'))
        ->toContain('MK Prodi Sendiri')
        ->not->toContain('MK Prodi Lain')
        ->and($service->jumlahKurikulum($this->timkur))->toBe(2);
});
