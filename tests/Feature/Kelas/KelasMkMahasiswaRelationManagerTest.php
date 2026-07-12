<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\EditKelasMk;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\RelationManagers\KelasMkMahasiswaRelationManager;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
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
    $this->seed(SemesterSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->adminProdi = User::where('username', 'adminprodi')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create(['kode' => 'IF101']);

    $this->kelas = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
    ]);

    $this->actingAs($this->adminProdi);
});

it('tombol daftarkan mahasiswa benar-benar mendaftarkan mahasiswa yang dipilih', function () {
    $mahasiswa = Mahasiswa::factory()->create([
        'nim' => '227000123',
        'nama' => 'Budi Santoso',
        'academic_unit_id' => $this->prodi->id,
    ]);

    Livewire::test(KelasMkMahasiswaRelationManager::class, [
        'ownerRecord' => $this->kelas,
        'pageClass' => EditKelasMk::class,
    ])->callTableAction('attach', data: ['recordId' => [$mahasiswa->id]]);

    expect(KelasMkMahasiswa::query()
        ->where('kelas_mk_id', $this->kelas->id)
        ->where('mahasiswa_id', $mahasiswa->id)
        ->exists())->toBeTrue();
});

it('opsi pilih mahasiswa pada tombol daftarkan menampilkan nama dan nim, bukan label generik', function () {
    $mahasiswa = Mahasiswa::factory()->create([
        'nim' => '227000123',
        'nama' => 'Budi Santoso',
        'academic_unit_id' => $this->prodi->id,
    ]);

    $component = Livewire::test(KelasMkMahasiswaRelationManager::class, [
        'ownerRecord' => $this->kelas,
        'pageClass' => EditKelasMk::class,
    ]);

    $action = $component->instance()->getTable()->getAction('attach');

    expect($action->getRecordTitle($mahasiswa))->toBe('Budi Santoso (227000123)');
});

it('tombol batalkan pendaftaran tersembunyi bila mahasiswa sudah dinilai', function () {
    $mahasiswaBelumDinilai = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $mahasiswaSudahDinilai = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);

    $this->kelas->mahasiswas()->attach($mahasiswaBelumDinilai->id);
    $this->kelas->mahasiswas()->attach($mahasiswaSudahDinilai->id, ['nilai_angka' => 85]);

    Livewire::test(KelasMkMahasiswaRelationManager::class, [
        'ownerRecord' => $this->kelas,
        'pageClass' => EditKelasMk::class,
    ])
        ->assertTableActionVisible('detach', $mahasiswaBelumDinilai)
        ->assertTableActionHidden('detach', $mahasiswaSudahDinilai);
});
