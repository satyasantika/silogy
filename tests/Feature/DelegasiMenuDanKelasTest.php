<?php

use App\Models\User;
use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\CPL\Filament\Resources\CplResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\EditKelasMk;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\RelationManagers\KelasMkMahasiswaRelationManager;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kelas\Policies\KelasMkPolicy;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
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

    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->adminProdi = User::query()->where('username', 'adminprodi')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();
});

function kelasDariMkUniv($test): KelasMk
{
    // MK milik universitas yang diadaptasi prodi (penawaran di prodi).
    $mkUniv = Mk::factory()->create(['academic_unit_id' => $test->univ->id, 'nama' => 'MK Penciri Universitas']);
    $mkUnit = MkUnit::factory()->forMk($mkUniv)->forAcademicUnit($test->prodi)->create(['kode' => 'ADP101']);

    return KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $test->semester->id,
        'kode_kelas' => 'A',
    ]);
}

it('admin prodi dapat menetapkan dosen pengampu pada kelas mk hasil adaptasi mk universitas', function () {
    $kelas = kelasDariMkUniv($this);
    $dosen = User::query()->where('username', 'dosen')->firstOrFail();

    $this->actingAs($this->adminProdi);

    Livewire::test(EditKelasMk::class, ['record' => $kelas->id])
        ->fillForm(['dosen_pengampu_id' => $dosen->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($kelas->fresh()->dosen_pengampu_id)->toBe($dosen->id);
});

it('admin prodi dapat mengimpor mahasiswa via daftar nim ke kelas mk', function () {
    $this->seed(MahasiswaSeeder::class);
    $kelas = kelasDariMkUniv($this);
    $duaMahasiswa = Mahasiswa::query()
        ->where('academic_unit_id', $this->prodi->id)
        ->limit(2)
        ->pluck('nim');

    $this->actingAs($this->adminProdi);

    Livewire::test(KelasMkMahasiswaRelationManager::class, [
        'ownerRecord' => $kelas,
        'pageClass' => EditKelasMk::class,
    ])
        ->callTableAction('bulkImport', data: [
            'rows' => $duaMahasiswa->implode("\n")."\nNIM-TIDAK-ADA",
            'mode_duplikat' => 'lewati',
        ]);

    expect(KelasMkMahasiswa::query()->where('kelas_mk_id', $kelas->id)->count())->toBe(2);
});

it('pembuatan kelas mk hanya untuk penugasan langsung di prodi', function () {
    $policy = new KelasMkPolicy;

    expect($policy->create($this->adminProdi))->toBeTrue()
        ->and($policy->create(User::where('username', 'timkur')->firstOrFail()))->toBeTrue()
        ->and($policy->create(User::where('username', 'adminfak')->firstOrFail()))->toBeFalse()
        ->and($policy->create(User::where('username', 'timkurfak')->firstOrFail()))->toBeFalse();
});

it('superadmin tidak lagi melihat menu kategori terdelegasi', function () {
    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());

    expect(KurikulumResource::shouldRegisterNavigation())->toBeFalse()
        ->and(CplResource::shouldRegisterNavigation())->toBeFalse()
        ->and(BokResource::shouldRegisterNavigation())->toBeFalse()
        ->and(MkUnitResource::shouldRegisterNavigation())->toBeFalse()
        ->and(KelasMkResource::shouldRegisterNavigation())->toBeFalse()
        ->and(CpmkResource::shouldRegisterNavigation())->toBeFalse()
        ->and(KomponenPenilaianResource::shouldRegisterNavigation())->toBeFalse();
});

it('admin melihat menu kurikulum, mata kuliah, penilaian, dan kelas sesuai delegasi', function () {
    // Scope menu MK/penilaian hidup ketika ada MK & kelas pada unit penugasan.
    kelasDariMkUniv($this);
    Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);

    $this->actingAs($this->adminProdi);

    expect(KurikulumResource::shouldRegisterNavigation())->toBeTrue()
        ->and(CplResource::shouldRegisterNavigation())->toBeTrue()
        ->and(CpmkResource::shouldRegisterNavigation())->toBeTrue()
        ->and(KomponenPenilaianResource::shouldRegisterNavigation())->toBeTrue()
        ->and(KelasMkResource::shouldRegisterNavigation())->toBeTrue();
});

it('koordinator mk tetap melihat menu mata kuliah dan penilaian', function () {
    $this->actingAs(User::query()->where('username', 'korma')->firstOrFail());

    // Scope korma hidup lewat MK & kelas yang ia koordinasikan.
    $korma = User::where('username', 'korma')->firstOrFail();
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $korma->id,
    ]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create(['kode' => 'KOR101']);
    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'koordinator_mk_id' => $korma->id,
    ]);

    expect(CpmkResource::shouldRegisterNavigation())->toBeTrue()
        ->and(KomponenPenilaianResource::shouldRegisterNavigation())->toBeTrue();
});
