<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Filament\Resources\MkResource\Pages\ListMks;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\SemesterKontrakPenawaran;
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

    session()->forget(SemesterKontrakPenawaran::SESSION_KEY);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->semesterAktif = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->semesterLain = Semester::query()
        ->whereKeyNot($this->semesterAktif->id)
        ->orderByDesc('kode')
        ->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum List Mk Kelas',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);
    SemesterKontrakPenawaran::set($this->semesterAktif->id);

    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Algoritma dan Pemrograman',
    ]);
    $this->mkTanpaPenawaran = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'MK Tanpa Penawaran',
    ]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forKurikulum($this->kurikulum)->create([
        'kode' => 'IF101',
    ]);

    $this->actingAs($this->timkur);
});

it('list mk tanpa aksi ubah dan menampilkan kolom dikontrak', function () {
    Livewire::test(ListMks::class)
        ->loadTable()
        ->assertSee('Dikontrak')
        ->assertSee('—')
        ->assertDontSee('0 kelas')
        ->assertDontSee('0 mahasiswa')
        ->assertTableActionDoesNotExist('edit')
        ->assertCanSeeTableRecords([$this->mk, $this->mkTanpaPenawaran]);
});

it('kolom dikontrak menampilkan badge kelas dan mahasiswa lewat penawaran mk per semester', function () {
    $kelasA = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semesterAktif->id,
        'kode_kelas' => 'A',
    ]);
    $kelasB = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semesterAktif->id,
        'kode_kelas' => 'B',
    ]);
    KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semesterLain->id,
        'kode_kelas' => 'Z',
    ]);

    $mhs1 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '259255112001']);
    $mhs2 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '259255112002']);
    $mhs3 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '259255112003']);

    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasA->id, 'mahasiswa_id' => $mhs1->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasA->id, 'mahasiswa_id' => $mhs2->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasB->id, 'mahasiswa_id' => $mhs3->id]);

    SemesterKontrakPenawaran::set($this->semesterAktif->id);

    $halaman = Livewire::test(ListMks::class)->loadTable();
    $baris = $halaman->instance()->getFilteredTableQuery()->get()->keyBy('id');

    expect((int) $baris[$this->mk->id]->jumlah_kelas)->toBe(2)
        ->and((int) $baris[$this->mk->id]->jumlah_mahasiswa)->toBe(3)
        ->and((int) $baris[$this->mkTanpaPenawaran->id]->jumlah_kelas)->toBe(0)
        ->and((int) $baris[$this->mkTanpaPenawaran->id]->jumlah_mahasiswa)->toBe(0);

    $halaman
        ->assertSee('2')
        ->assertSee('kelas')
        ->assertSee('3')
        ->assertSee('mahasiswa')
        ->assertSeeHtml('silogy-dikontrak')
        ->assertSee('—');

    $halamanLain = Livewire::test(ListMks::class)
        ->set('tableFilters.semester_kontrak_penawaran.value', $this->semesterLain->id)
        ->loadTable();
    $barisLain = $halamanLain->instance()->getFilteredTableQuery()->get()->keyBy('id');

    expect((int) $barisLain[$this->mk->id]->jumlah_kelas)->toBe(1)
        ->and((int) $barisLain[$this->mk->id]->jumlah_mahasiswa)->toBe(0)
        ->and(SemesterKontrakPenawaran::currentId())->toBe($this->semesterLain->id);

    $halamanLain
        ->assertSeeHtml('silogy-dikontrak')
        ->assertSee('1')
        ->assertSee('mahasiswa');
});

it('kolom dikontrak menjumlah kontrak lintas penawaran semua prodi pada semester terpilih', function () {
    $fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $prodiLain = AcademicUnit::query()->create([
        'parent_id' => $fakultas->id,
        'type' => 'study_program',
        'code' => 'PRODI-DK',
        'nama' => 'Prodi Dikontrak Uji',
        'status' => 'aktif',
    ]);
    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $prodiLain->id,
        'nama' => 'Kurikulum Prodi Dikontrak',
        'tahun' => 2025,
        'is_active' => true,
    ]);

    $mkUnitLain = MkUnit::factory()->forMk($this->mk)->forKurikulum($kurikulumLain)->create([
        'kode' => 'IF101-B',
        'academic_unit_id' => $prodiLain->id,
    ]);

    $kelasProdi = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semesterAktif->id,
        'kode_kelas' => 'A',
    ]);
    $kelasProdiLain = KelasMk::query()->create([
        'mk_unit_id' => $mkUnitLain->id,
        'semester_id' => $this->semesterAktif->id,
        'kode_kelas' => 'X',
    ]);

    $mhs1 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '259255112011']);
    $mhs2 = Mahasiswa::factory()->create(['academic_unit_id' => $prodiLain->id, 'nim' => '259255112012']);

    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasProdi->id, 'mahasiswa_id' => $mhs1->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasProdiLain->id, 'mahasiswa_id' => $mhs2->id]);

    SemesterKontrakPenawaran::set($this->semesterAktif->id);

    $baris = Livewire::test(ListMks::class)
        ->loadTable()
        ->instance()
        ->getFilteredTableQuery()
        ->get()
        ->keyBy('id');

    expect((int) $baris[$this->mk->id]->jumlah_kelas)->toBe(2)
        ->and((int) $baris[$this->mk->id]->jumlah_mahasiswa)->toBe(2);
});
