<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\CreateKelasMk;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\EditKelasMk;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\ListKelasMks;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\MahasiswaSeeder;
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
    $this->seed(MahasiswaSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $this->adminProdi = User::where('username', 'adminprodi')->first();
    $this->dosen = User::where('username', 'dosen')->first();
    $this->semester = Semester::query()->where('status_aktif', true)->first();

    $this->mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create([
        'kode' => 'IF101',
    ]);

    $this->actingAs($this->adminProdi);
});

it('admin prodi dapat membuat dua kelas dan menetapkan dosen', function () {
    foreach (['A', 'B'] as $kodeKelas) {
        Livewire::test(CreateKelasMk::class)
            ->fillForm([
                'mk_unit_id' => $this->mkUnit->id,
                'semester_id' => $this->semester->id,
                'kode_kelas' => $kodeKelas,
                'dosen_pengampu_id' => $this->dosen->id,
                'kapasitas' => 40,
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    expect(KelasMk::query()->count())->toBe(2)
        ->and(KelasMk::query()->where('dosen_pengampu_id', $this->dosen->id)->count())->toBe(2);
});

it('admin prodi dapat mendaftarkan mahasiswa via attach pada relation manager', function () {
    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);

    $mahasiswaIds = Mahasiswa::query()
        ->where('academic_unit_id', $this->prodi->id)
        ->limit(3)
        ->pluck('id')
        ->all();

    Livewire::test(EditKelasMk::class, ['record' => $kelas->getKey()])
        ->assertSuccessful();

    $kelas->mahasiswas()->attach($mahasiswaIds);

    expect($kelas->mahasiswas()->count())->toBe(3);

    Livewire::test(ListKelasMks::class)
        ->assertSuccessful();
});
