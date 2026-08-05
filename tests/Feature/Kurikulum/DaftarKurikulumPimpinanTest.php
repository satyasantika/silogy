<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Filament\Widgets\PimpinanKpiWidget;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Services\DashboardPimpinanService;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Filament\Pages\DaftarKurikulumPimpinan;
use App\Modules\Kurikulum\Filament\Pages\HasilAnalisisCpl;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->kaprodi = User::query()->where('username', 'kaprodi')->firstOrFail();
    $this->dekan = User::query()->where('username', 'dekan')->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Card Pimpinan',
        'tahun' => 2026,
        'is_active' => true,
        'kode' => 'KUR-PIMP-01',
    ]);
});

it('pimpinan melihat menu kurikulum dan tidak melihat menu mahasiswa', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');

    expect(DaftarKurikulumPimpinan::shouldRegisterNavigation())->toBeTrue()
        ->and(MahasiswaResource::shouldRegisterNavigation())->toBeFalse();
});

it('KPI kepemimpinan mengarah ke daftar kurikulum pimpinan', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');

    $url = DaftarKurikulumPimpinan::getUrl();

    Livewire::test(PimpinanKpiWidget::class)
        ->assertSuccessful()
        ->assertSee('Kurikulum Aktif')
        ->assertSeeHtml(e($url));
});

it('card kurikulum pimpinan punya aksi kerjakan dan badge laporan dengan ikon', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');

    Cpl::factory()->forKurikulum($this->kurikulum)->create(['kode' => 'CPL-PIMP']);

    KurikulumTerpilih::set($this->kurikulum->id);

    Livewire::test(DaftarKurikulumPimpinan::class)
        ->loadTable()
        ->assertSee('Sedang dikerjakan')
        ->assertSeeHtml('silogy-kurikulum-card-sedang-dikerjakan')
        ->assertSee('Hasil CPL', escape: false)
        ->assertSee('Grafik CPL', escape: false)
        ->assertSee('Per Mahasiswa', escape: false)
        ->assertDontSee('Hasil CPL · Ada', escape: false)
        ->assertDontSee('Grafik CPL · Ada', escape: false)
        ->assertDontSee('Per Mahasiswa · Ada', escape: false)
        ->assertDontSee(' · Belum', escape: false)
        ->assertSeeHtml('silogy-menu-badge--hasil')
        ->assertSeeHtml('silogy-menu-badge--grafik')
        ->assertSeeHtml('silogy-menu-badge--mahasiswa')
        ->assertSeeHtml('silogy-laporan-kurikulum-kpi--card')
        ->assertSee('Penilaian mata kuliah')
        ->assertSee('Penilaian mahasiswa')
        ->assertDontSee('Profil ·', escape: false)
        ->assertDontSee('BoK ·', escape: false)
        ->assertSeeHtml('navigasi-kurikulum-pimpinan');

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Alternatif Pimpinan',
        'tahun' => 2027,
        'is_active' => false,
    ]);

    Livewire::test(DaftarKurikulumPimpinan::class)
        ->loadTable()
        ->callTableAction('kerjakan', $kurikulumLain);

    expect(KurikulumTerpilih::currentId())->toBe($kurikulumLain->id);
});

it('dekan melihat kurikulum prodi di bawah fakultas pada daftar pimpinan', function () {
    $this->actingAs($this->dekan);
    ActiveRole::set('Pimpinan');

    Livewire::test(DaftarKurikulumPimpinan::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$this->kurikulum]);
});

it('badge laporan pimpinan mengunci kurikulum terpilih dan mengalihkan ke hasil analisis', function () {
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');

    $response = $this->get(route('silogy.kurikulum-navigasi-pimpinan', [
        'kurikulum' => $this->kurikulum->id,
        'menu' => 'hasil',
    ]));

    $response->assertRedirect(HasilAnalisisCpl::getUrl());
    expect(KurikulumTerpilih::currentId())->toBe($this->kurikulum->id);
});

it('kpi card prodi menghitung progress dari penawaran kurikulum tersebut', function () {
    $this->seed(SemesterSeeder::class);
    $this->actingAs($this->kaprodi);
    ActiveRole::set('Pimpinan');

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $mkA = Mk::factory()->forKurikulum($this->kurikulum)->create(['nama' => 'MK Progress A']);
    $mkB = Mk::factory()->forKurikulum($this->kurikulum)->create(['nama' => 'MK Progress B']);
    $mkUnitA = MkUnit::factory()->forMk($mkA)->forKurikulum($this->kurikulum)->create([
        'kode' => 'MK-PIMP-A',
    ]);
    $mkUnitB = MkUnit::factory()->forMk($mkB)->forKurikulum($this->kurikulum)->create([
        'kode' => 'MK-PIMP-B',
    ]);

    $kelasA = KelasMk::query()->create([
        'mk_unit_id' => $mkUnitA->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);
    $kelasB = KelasMk::query()->create([
        'mk_unit_id' => $mkUnitB->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'B',
    ]);

    $mhs1 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $mhs2 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);

    KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasA->id,
        'mahasiswa_id' => $mhs1->id,
        'nilai_angka' => 80,
        'nilai_huruf' => 'A',
    ]);
    KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasB->id,
        'mahasiswa_id' => $mhs2->id,
        'nilai_angka' => null,
        'nilai_huruf' => null,
    ]);

    $rekap = app(DashboardPimpinanService::class)
        ->rekapProgressPenilaianUntukKurikulum($this->kurikulum->fresh(['academicUnit']));

    expect($rekap['mk_total'])->toBe(2)
        ->and($rekap['mk_dinilai'])->toBe(1)
        ->and($rekap['mk_progress_persen'])->toBe(50)
        ->and($rekap['mahasiswa_total'])->toBe(2)
        ->and($rekap['mahasiswa_dinilai'])->toBe(1)
        ->and($rekap['mahasiswa_progress_persen'])->toBe(50);

    Livewire::test(DaftarKurikulumPimpinan::class)
        ->loadTable()
        ->assertSeeHtml('silogy-laporan-kurikulum-kpi--card')
        ->assertSee('50%')
        ->assertSee('1 dari 2 mata kuliah sudah dinilai')
        ->assertSee('1 dari 2 kontrak sudah dinilai');
});

it('kpi card fakultas merollup penawaran prodi yang mengadaptasi MK kurikulum induk', function () {
    $this->seed(SemesterSeeder::class);
    $this->actingAs($this->dekan);
    ActiveRole::set('Pimpinan');

    $fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $kurikulumFak = Kurikulum::query()->create([
        'academic_unit_id' => $fakultas->id,
        'nama' => 'Kurikulum Fakultas KPI',
        'tahun' => 2026,
        'is_active' => true,
        'kode' => 'KUR-FAK-KPI',
    ]);

    $mkFak = Mk::factory()->forKurikulum($kurikulumFak)->create(['nama' => 'MK Fakultas Adaptasi']);
    $mkUnitProdi = MkUnit::factory()->forMk($mkFak)->forKurikulum($this->kurikulum)->create([
        'kode' => 'MK-ADAPT-01',
        'is_active' => true,
        'academic_unit_id' => $this->prodi->id,
    ]);

    // MK lokal prodi — tidak boleh masuk rollup fakultas.
    $mkLokal = Mk::factory()->forKurikulum($this->kurikulum)->create(['nama' => 'MK Lokal Prodi']);
    $mkUnitLokal = MkUnit::factory()->forMk($mkLokal)->forKurikulum($this->kurikulum)->create([
        'kode' => 'MK-LOKAL-01',
        'is_active' => true,
    ]);

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $kelasAdapt = KelasMk::query()->create([
        'mk_unit_id' => $mkUnitProdi->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);
    $kelasLokal = KelasMk::query()->create([
        'mk_unit_id' => $mkUnitLokal->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'B',
    ]);

    $mhsAdapt = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $mhsLokal = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);

    KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasAdapt->id,
        'mahasiswa_id' => $mhsAdapt->id,
        'nilai_angka' => 75,
        'nilai_huruf' => 'B',
    ]);
    KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasLokal->id,
        'mahasiswa_id' => $mhsLokal->id,
        'nilai_angka' => 90,
        'nilai_huruf' => 'A',
    ]);

    $rekap = app(DashboardPimpinanService::class)
        ->rekapProgressPenilaianUntukKurikulum($kurikulumFak->fresh(['academicUnit']));

    expect($rekap['mk_total'])->toBe(1)
        ->and($rekap['mk_dinilai'])->toBe(1)
        ->and($rekap['mk_progress_persen'])->toBe(100)
        ->and($rekap['mahasiswa_total'])->toBe(1)
        ->and($rekap['mahasiswa_dinilai'])->toBe(1)
        ->and($rekap['mahasiswa_progress_persen'])->toBe(100);

    Livewire::test(DaftarKurikulumPimpinan::class)
        ->loadTable()
        ->assertSee('Kurikulum Fakultas KPI')
        ->assertSeeHtml('silogy-laporan-kurikulum-kpi--card');
});
