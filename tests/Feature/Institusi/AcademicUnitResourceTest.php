<?php

use App\Models\User;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages\CreateAcademicUnit;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages\ListAcademicUnits;
use App\Modules\Institusi\Models\AcademicUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::where('username', 'superadmin')->first());
});

it('superadmin dapat mengakses resource unit akademik', function () {
    expect(AcademicUnit::count())->toBe(4)
        ->and(AcademicUnitResource::canViewAny())->toBeTrue();
});

it('jenisDanNamaUntukCard memberi label jenis untuk universitas fakultas dan prodi tanpa mengulang awalan nama', function () {
    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    expect(AcademicUnitResource::jenisDanNamaUntukCard($univ))
        ->toBe(['Universitas', 'Siliwangi'])
        ->and(AcademicUnitResource::jenisDanNamaUntukCard($fakultas))
        ->toBe(['Fakultas', 'Keguruan dan Ilmu Pendidikan'])
        ->and(AcademicUnitResource::jenisDanNamaUntukCard($prodi))
        ->toBe(['Program Studi', 'S1 Pendidikan Matematika']);
});

it('menampilkan 4 unit akademik hasil seed di tabel', function () {
    Livewire::test(ListAcademicUnits::class)
        ->assertCanSeeTableRecords(AcademicUnit::all())
        ->assertSuccessful();
});

it('dapat membuat fakultas baru di bawah universitas', function () {
    $univ = AcademicUnit::where('type', 'university')->first();

    Livewire::test(CreateAcademicUnit::class)
        ->fillForm([
            'type' => 'faculty',
            'parent_id' => $univ->id,
            'code' => 'FK',
            'nama' => 'Fakultas Keguruan',
            'status' => 'aktif',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(AcademicUnit::where('code', 'FK')->exists())->toBeTrue();
});

it('dapat membuat program studi langsung di bawah fakultas tanpa jurusan', function () {
    $fakultas = AcademicUnit::where('type', 'faculty')->first();

    Livewire::test(CreateAcademicUnit::class)
        ->fillForm([
            'type' => 'study_program',
            'parent_id' => $fakultas->id,
            'code' => 'PSTI',
            'nama' => 'Program Studi Teknik Informatika Baru',
            'status' => 'aktif',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $prodi = AcademicUnit::where('code', 'PSTI')->first();

    expect($prodi)->not->toBeNull()
        ->and($prodi->parent_id)->toBe($fakultas->id)
        ->and($prodi->parent->isFaculty())->toBeTrue();
});

it('tabel menampilkan kolom jenjang untuk unit berjenis prodi', function () {
    $fakultas = AcademicUnit::where('type', 'faculty')->first();

    $prodi = AcademicUnit::factory()->studyProgram($fakultas)->create([
        'code' => 'PSJENJANG',
        'nama' => 'Prodi Uji Jenjang',
        'jenjang' => 'S2',
    ]);

    Livewire::test(ListAcademicUnits::class)
        ->assertTableColumnStateSet('jenjang', 'S2', $prodi)
        ->assertSee('S2');
});

it('program studi tetap dapat berinduk ke jurusan', function () {
    $jurusan = AcademicUnit::where('type', 'department')->first();

    Livewire::test(CreateAcademicUnit::class)
        ->fillForm([
            'type' => 'study_program',
            'parent_id' => $jurusan->id,
            'code' => 'PSJR',
            'nama' => 'Program Studi Di Bawah Jurusan',
            'status' => 'aktif',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $prodi = AcademicUnit::where('code', 'PSJR')->first();

    expect($prodi)->not->toBeNull()
        ->and($prodi->parent->isJurusan())->toBeTrue();
});
