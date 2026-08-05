<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Resources\PesertaKelasResource\Pages\ListPesertaKelas;
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
    $this->seed(SemesterSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->dosen = User::query()->where('username', 'dosen')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->semesterLain = Semester::query()
        ->whereKeyNot($this->semester->id)
        ->orderByDesc('kode')
        ->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Peserta KPI',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
        'nama' => 'Aljabar Linier Lanjut',
    ]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create([
        'kode' => 'MAT309',
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);
    SemesterTerpilih::set($this->mk->id, $this->semester->id);
});

it('menampilkan kpi bento semester terpilih dan semua semester', function () {
    $kelasA = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);
    $kelasB = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'B',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);
    $kelasLalu = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semesterLain->id,
        'kode_kelas' => 'C',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);

    $mhs1 = Mahasiswa::query()->create([
        'nim' => '227000101',
        'nama' => 'Mahasiswa Satu',
        'academic_unit_id' => $this->prodi->id,
    ]);
    $mhs2 = Mahasiswa::query()->create([
        'nim' => '227000102',
        'nama' => 'Mahasiswa Dua',
        'academic_unit_id' => $this->prodi->id,
    ]);
    $mhs3 = Mahasiswa::query()->create([
        'nim' => '227000103',
        'nama' => 'Mahasiswa Tiga',
        'academic_unit_id' => $this->prodi->id,
    ]);

    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasA->id, 'mahasiswa_id' => $mhs1->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasA->id, 'mahasiswa_id' => $mhs2->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasB->id, 'mahasiswa_id' => $mhs3->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasLalu->id, 'mahasiswa_id' => $mhs1->id]);

    $component = Livewire::test(ListPesertaKelas::class);

    $rekap = $component->instance()->rekapKpiPesertaKelas();

    expect($rekap['tampil_semester'])->toBeTrue()
        ->and($rekap['semester_kelas'])->toBe(2)
        ->and($rekap['semester_mahasiswa'])->toBe(3)
        ->and($rekap['semua_kelas'])->toBe(3)
        ->and($rekap['semua_mahasiswa'])->toBe(4);

    $component
        ->assertSeeHtml('data-silogy="peserta-kelas-panel"')
        ->assertSeeHtml('data-silogy="banner-mk-header-panel"')
        ->assertSeeHtml('data-silogy="peserta-kelas-bento"')
        ->assertSee('Mata kuliah yang dikerjakan')
        ->assertSee('Aljabar Linier Lanjut')
        ->assertSee('Semester terpilih')
        ->assertSee('Semua semester')
        ->assertSee('Tarik data')
        ->assertCanSeeTableRecords([$kelasA, $kelasB])
        ->assertCanNotSeeTableRecords([$kelasLalu])
        ->assertTableColumnExists('kode_kelas')
        ->assertTableColumnExists('dosenPengampu.full_name')
        ->assertTableColumnExists('mahasiswas_count')
        ->assertTableColumnDoesNotExist('mkUnit.kode')
        ->assertTableColumnDoesNotExist('mkUnit.mk.nama');
});

it('menggabungkan banner, bento, toolbar, dan tabel dalam satu kartu', function () {
    $html = Livewire::test(ListPesertaKelas::class)->html();

    expect(strpos($html, 'data-silogy="peserta-kelas-panel"'))
        ->toBeLessThan(strpos($html, 'data-silogy="banner-mk-header-panel"'))
        ->and(strpos($html, 'data-silogy="banner-mk-header-panel"'))
        ->toBeLessThan(strpos($html, 'data-silogy="peserta-kelas-bento"'))
        ->and(strpos($html, 'data-silogy="peserta-kelas-bento"'))
        ->toBeLessThan(strpos($html, 'importSintesysPesertaKelas'))
        ->and(substr_count($html, 'data-silogy="peserta-kelas-panel"'))->toBe(1);

    Livewire::test(ListPesertaKelas::class)
        ->assertTableActionExists('importSintesysPesertaKelas')
        ->assertActionDoesNotExist('importSintesysPesertaKelas');
});

it('mencari kelas berdasarkan kode kelas dan nama dosen pengampu', function () {
    $kelasA = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);
    $kelasZ = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'Z',
        'dosen_pengampu_id' => null,
    ]);

    Livewire::test(ListPesertaKelas::class)
        ->searchTable('A')
        ->assertCanSeeTableRecords([$kelasA])
        ->assertCanNotSeeTableRecords([$kelasZ])
        ->searchTable($this->dosen->full_name)
        ->assertCanSeeTableRecords([$kelasA])
        ->assertCanNotSeeTableRecords([$kelasZ]);
});
