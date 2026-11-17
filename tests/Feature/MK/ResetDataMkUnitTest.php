<?php

use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Filament\Resources\MkResource\Pages\EditMk;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\EditMkUnit;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Services\MkUnitResetService;
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
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Reset',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create(['nama' => 'Matematika Diskrit']);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forKurikulum($this->kurikulum)->create(['kode' => 'KP21514099']);

    $this->kelas = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
    ]);
    $mhs = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '259255111101']);
    $this->peserta = KelasMkMahasiswa::query()->create(['kelas_mk_id' => $this->kelas->id, 'mahasiswa_id' => $mhs->id]);

    $this->actingAs($this->timkur);
});

it('ringkasan menghitung kelas dan mahasiswa yang akan terhapus', function () {
    $ringkasan = app(MkUnitResetService::class)->ringkasan($this->mkUnit);

    expect($ringkasan['kelas'])->toBe(1)
        ->and($ringkasan['mahasiswa'])->toBe(1)
        ->and(app(MkUnitResetService::class)->adaData($this->mkUnit))->toBeTrue();
});

it('reset menghapus kelas, peserta, dan hasil turunannya tetapi mempertahankan MK dan penawarannya', function () {
    $hasil = app(MkUnitResetService::class)->reset($this->mkUnit);

    expect($hasil['kelas'])->toBe(1)
        ->and(KelasMk::query()->where('mk_unit_id', $this->mkUnit->id)->exists())->toBeFalse()
        ->and(KelasMkMahasiswa::query()->whereKey($this->peserta->id)->exists())->toBeFalse()
        ->and(MkUnit::query()->whereKey($this->mkUnit->id)->exists())->toBeTrue()
        ->and(Mk::query()->whereKey($this->mk->id)->exists())->toBeTrue()
        ->and(app(MkUnitResetService::class)->adaData($this->mkUnit))->toBeFalse();
});

it('reset tidak menyentuh kelas milik penawaran lain', function () {
    $lain = MkUnit::factory()->forMk(Mk::factory()->forKurikulum($this->kurikulum)->create())->forKurikulum($this->kurikulum)->create();
    $kelasLain = KelasMk::query()->create(['mk_unit_id' => $lain->id, 'semester_id' => $this->semester->id, 'kode_kelas' => 'A']);

    app(MkUnitResetService::class)->reset($this->mkUnit);

    expect(KelasMk::query()->whereKey($kelasLain->id)->exists())->toBeTrue();
});

it('reset membuang agregat CPL yang basi untuk unit dan induknya', function () {
    $cpl = Cpl::factory()->create(['academic_unit_id' => $this->prodi->id, 'kurikulum_id' => $this->kurikulum->id]);
    HasilCplMkUnit::query()->create([
        'cpl_id' => $cpl->id, 'mk_id' => $this->mk->id, 'academic_unit_id' => $this->prodi->id,
        'semester_id' => $this->semester->id, 'rata_rata' => 80, 'persentase_tercapai' => 100, 'jumlah_mahasiswa' => 1,
    ]);
    HasilCplUnit::query()->create([
        'cpl_id' => $cpl->id, 'academic_unit_id' => $this->prodi->id,
        'semester_id' => $this->semester->id, 'rata_rata' => 80, 'persentase_tercapai' => 100, 'jumlah_mahasiswa' => 1,
    ]);

    app(MkUnitResetService::class)->reset($this->mkUnit);

    expect(HasilCplMkUnit::query()->where('academic_unit_id', $this->prodi->id)->count())->toBe(0)
        ->and(HasilCplUnit::query()->where('academic_unit_id', $this->prodi->id)->count())->toBe(0);
});

it('Tim Kurikulum boleh mereset lewat halaman edit, dan kode MK harus diketik', function () {
    Livewire::test(EditMkUnit::class, ['record' => $this->mkUnit->getKey()])
        ->assertActionVisible('resetDataMk')
        ->callAction('resetDataMk', ['konfirmasi_kode' => 'salah'])
        ->assertHasActionErrors(['konfirmasi_kode']);

    expect(KelasMk::query()->where('mk_unit_id', $this->mkUnit->id)->exists())->toBeTrue();

    Livewire::test(EditMkUnit::class, ['record' => $this->mkUnit->getKey()])
        ->callAction('resetDataMk', ['konfirmasi_kode' => 'KP21514099'])
        ->assertHasNoActionErrors();

    expect(KelasMk::query()->where('mk_unit_id', $this->mkUnit->id)->exists())->toBeFalse();
});

it('halaman edit menjelaskan prosedur reset dan hapus', function () {
    Livewire::test(EditMkUnit::class, ['record' => $this->mkUnit->getKey()])
        ->assertSee('Prosedur reset dan hapus mata kuliah')
        ->assertSee('Reset data MK');
});

it('Tim Kurikulum tidak boleh menghapus selama masih ada data kontrak atau penilaian', function () {
    expect($this->timkur->can('delete', $this->mkUnit))->toBeFalse();

    Livewire::test(EditMkUnit::class, ['record' => $this->mkUnit->getKey()])
        ->assertActionHidden('delete');
});

it('setelah direset Tim Kurikulum boleh menghapus penawaran MK', function () {
    app(MkUnitResetService::class)->reset($this->mkUnit);

    expect($this->timkur->fresh()->can('delete', $this->mkUnit))->toBeTrue();

    Livewire::test(EditMkUnit::class, ['record' => $this->mkUnit->getKey()])
        ->assertActionEnabled('delete')
        ->callAction('delete');

    expect(MkUnit::query()->whereKey($this->mkUnit->id)->exists())->toBeFalse();
});

// ── /mks/<id>/edit ────────────────────────────────────────────────────────

it('reset penawaran MK hanya menyentuh prodi dalam lingkup Tim Kurikulum dan melaporkan yang dilewati', function () {
    $prodiLain = AcademicUnit::factory()
        ->studyProgram($this->prodi->parent ?? $this->prodi)
        ->create(['nama' => 'Prodi Tetangga']);

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $prodiLain->id, 'nama' => 'Kurikulum Lain', 'tahun' => 2026, 'is_active' => true,
    ]);
    $penawaranLain = MkUnit::factory()->forMk($this->mk)->forKurikulum($kurikulumLain)->create([
        'kode' => 'KP99914098', 'academic_unit_id' => $prodiLain->id,
    ]);
    $kelasLain = KelasMk::query()->create(['mk_unit_id' => $penawaranLain->id, 'semester_id' => $this->semester->id, 'kode_kelas' => 'B']);

    $hasil = app(MkUnitResetService::class)->resetPenawaranMk($this->mk, $this->timkur);

    expect($hasil['penawaran_direset'])->toBe(1)
        ->and($hasil['penawaran_dilewati'])->toBe(1)
        ->and(KelasMk::query()->where('mk_unit_id', $this->mkUnit->id)->exists())->toBeFalse()
        ->and(KelasMk::query()->whereKey($kelasLain->id)->exists())->toBeTrue();

    // Selama penawaran prodi lain masih berisi data, MK belum boleh dihapus.
    expect($this->timkur->can('delete', $this->mk))->toBeFalse();
});

it('Tim Kurikulum mereset penawaran lewat halaman edit MK, kode MK harus diketik', function () {
    $edit = EditMk::class;

    Livewire::test($edit, ['record' => $this->mk->getKey()])
        ->assertActionVisible('resetPenawaran')
        ->assertSee('Prosedur reset dan hapus mata kuliah')
        ->callAction('resetPenawaran', ['konfirmasi_kode' => 'salah'])
        ->assertHasActionErrors(['konfirmasi_kode']);

    expect(KelasMk::query()->count())->toBe(1);

    Livewire::test($edit, ['record' => $this->mk->getKey()])
        ->callAction('resetPenawaran', ['konfirmasi_kode' => $this->mk->nama])
        ->assertHasNoActionErrors();

    expect(KelasMk::query()->count())->toBe(0)
        ->and(MkUnit::query()->whereKey($this->mkUnit->id)->exists())->toBeTrue();
});

it('Tim Kurikulum baru boleh menghapus MK setelah seluruh penawarannya direset', function () {
    expect($this->timkur->can('delete', $this->mk))->toBeFalse();

    Livewire::test(EditMk::class, ['record' => $this->mk->getKey()])
        ->assertActionHidden('delete');

    app(MkUnitResetService::class)->resetPenawaranMk($this->mk, $this->timkur);

    expect($this->timkur->fresh()->can('delete', $this->mk))->toBeTrue();

    Livewire::test(EditMk::class, ['record' => $this->mk->getKey()])
        ->callAction('delete');

    expect(Mk::query()->whereKey($this->mk->id)->exists())->toBeFalse()
        ->and(MkUnit::query()->where('mk_id', $this->mk->id)->exists())->toBeFalse();
});

it('hapus MK ikut menghapus relasinya dan membuang agregat CPL yang basi', function () {
    $cpl = Cpl::factory()->create(['academic_unit_id' => $this->prodi->id, 'kurikulum_id' => $this->kurikulum->id]);
    app(MkUnitResetService::class)->reset($this->mkUnit);
    HasilCplMkUnit::query()->create([
        'cpl_id' => $cpl->id, 'mk_id' => $this->mk->id, 'academic_unit_id' => $this->prodi->id,
        'semester_id' => $this->semester->id, 'rata_rata' => 80, 'persentase_tercapai' => 100, 'jumlah_mahasiswa' => 1,
    ]);

    // Super Admin menghapus langsung, tanpa reset lebih dulu.
    $this->mk->delete();

    expect(MkUnit::query()->whereKey($this->mkUnit->id)->exists())->toBeFalse()
        ->and(HasilCplMkUnit::query()->where('mk_id', $this->mk->id)->exists())->toBeFalse();
});

it('hapus penawaran oleh Super Admin ikut menghapus kelas, nilai, dan membangun ulang agregat', function () {
    $cpl = Cpl::factory()->create(['academic_unit_id' => $this->prodi->id, 'kurikulum_id' => $this->kurikulum->id]);
    HasilCplUnit::query()->create([
        'cpl_id' => $cpl->id, 'academic_unit_id' => $this->prodi->id,
        'semester_id' => $this->semester->id, 'rata_rata' => 80, 'persentase_tercapai' => 100, 'jumlah_mahasiswa' => 1,
    ]);

    $this->mkUnit->delete();

    expect(KelasMk::query()->where('mk_unit_id', $this->mkUnit->id)->exists())->toBeFalse()
        ->and(KelasMkMahasiswa::query()->whereKey($this->peserta->id)->exists())->toBeFalse()
        ->and(HasilCplUnit::query()->where('academic_unit_id', $this->prodi->id)->exists())->toBeFalse();
});
