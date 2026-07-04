<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\CreateKelasMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Filament\Resources\MkResource\Pages\EditMk;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\CreateMkUnit;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $this->univ = AcademicUnit::query()->where('type', 'university')->first();
    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->first();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $this->timkur = User::query()->where('username', 'timkur')->first();
    $this->korma = User::query()->where('username', 'korma')->first();
});

it('seeder menugaskan timkur sebagai tim kurikulum di prodi, fakultas, dan universitas', function () {
    foreach ([$this->prodi, $this->fakultas, $this->univ] as $unit) {
        expect(
            AcademicUnitUser::query()
                ->where('user_id', $this->timkur->id)
                ->where('academic_unit_id', $unit->id)
                ->where('status_tim_kurikulum', true)
                ->exists()
        )->toBeTrue();
    }
});

it('tim kurikulum dapat menetapkan koordinator pada mk', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);

    $this->actingAs($this->timkur);

    Livewire::test(EditMk::class, ['record' => $mk->id])
        ->fillForm(['koordinator_mk_id' => $this->korma->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($mk->fresh()->koordinator_mk_id)->toBe($this->korma->id);
});

it('kelas mk baru mewarisi koordinator default dari mk', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create();
    $semester = Semester::query()->where('status_aktif', true)->first();

    $this->actingAs(User::where('username', 'superadmin')->first());

    Livewire::test(CreateKelasMk::class)
        ->fillForm([
            'mk_unit_id' => $mkUnit->id,
            'semester_id' => $semester->id,
            'kode_kelas' => 'A',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $kelas = KelasMk::query()->where('mk_unit_id', $mkUnit->id)->first();

    expect($kelas)->not->toBeNull()
        ->and($kelas->koordinator_mk_id)->toBe($this->korma->id);
});

it('koordinator mendapat scope mk sebelum kelas pertama dibuat', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);

    expect(CpmkResource::scopedKoordinatorMkIds($this->korma)->contains($mk->id))->toBeTrue()
        ->and(CpmkResource::userCanManageMkAsKoordinator($this->korma, $mk->id))->toBeTrue();
});

it('tim kurikulum prodi dapat mengadaptasi mk universitas dengan kode dan semester sendiri', function () {
    $mkUniv = Mk::factory()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Pendidikan Pancasila',
    ]);

    $timkurProdi = buatTimkurProdi($this->prodi);
    $this->actingAs($timkurProdi);

    Livewire::test(CreateMkUnit::class)
        ->fillForm([
            'academic_unit_id' => $this->prodi->id,
            'mk_id' => $mkUniv->id,
            'kode' => 'PMT-UNV101',
            'semester_ke' => 3,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $penawaran = MkUnit::query()
        ->where('mk_id', $mkUniv->id)
        ->where('academic_unit_id', $this->prodi->id)
        ->first();

    expect($penawaran)->not->toBeNull()
        ->and($penawaran->kode)->toBe('PMT-UNV101')
        ->and($penawaran->semester_ke)->toBe(3);
});

it('tim kurikulum prodi dapat mengadaptasi mk fakultas', function () {
    $mkFak = Mk::factory()->create([
        'academic_unit_id' => $this->fakultas->id,
        'nama' => 'Metodologi Penelitian Pendidikan',
    ]);

    $timkurProdi = buatTimkurProdi($this->prodi);
    $this->actingAs($timkurProdi);

    Livewire::test(CreateMkUnit::class)
        ->fillForm([
            'academic_unit_id' => $this->prodi->id,
            'mk_id' => $mkFak->id,
            'kode' => 'PMT-FAK101',
            'semester_ke' => 5,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(
        MkUnit::query()
            ->where('mk_id', $mkFak->id)
            ->where('academic_unit_id', $this->prodi->id)
            ->exists()
    )->toBeTrue();
});

it('tim kurikulum prodi tidak dapat membuat penawaran untuk unit lain', function () {
    $mkUniv = Mk::factory()->create(['academic_unit_id' => $this->univ->id]);

    $timkurProdi = buatTimkurProdi($this->prodi);
    $this->actingAs($timkurProdi);

    Livewire::test(CreateMkUnit::class)
        ->fillForm([
            'academic_unit_id' => $this->fakultas->id,
            'mk_id' => $mkUniv->id,
            'kode' => 'ILEGAL-1',
            'semester_ke' => 1,
        ])
        ->call('create')
        ->assertHasFormErrors(['academic_unit_id']);

    expect(MkUnit::query()->where('kode', 'ILEGAL-1')->exists())->toBeFalse();
});

it('mk yang sama tidak dapat ditawarkan dua kali pada unit yang sama', function () {
    $mkUniv = Mk::factory()->create(['academic_unit_id' => $this->univ->id]);
    MkUnit::factory()->forMk($mkUniv)->forAcademicUnit($this->prodi)->create(['kode' => 'SUDAH-ADA']);

    $timkurProdi = buatTimkurProdi($this->prodi);
    $this->actingAs($timkurProdi);

    Livewire::test(CreateMkUnit::class)
        ->fillForm([
            'academic_unit_id' => $this->prodi->id,
            'mk_id' => $mkUniv->id,
            'kode' => 'KODE-LAIN',
            'semester_ke' => 2,
        ])
        ->call('create')
        ->assertHasFormErrors(['mk_id']);
});

function buatTimkurProdi(AcademicUnit $prodi): User
{
    $user = User::create([
        'full_name' => 'Timkur Prodi Uji',
        'username' => 'timkurprodi'.Str::random(4),
        'email' => Str::random(8).'@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->assignRole(Role::where('name', 'Tim Kurikulum')->firstOrFail());

    AcademicUnitUser::create([
        'id' => (string) Str::uuid(),
        'academic_unit_id' => $prodi->id,
        'user_id' => $user->id,
        'status_pimpinan' => false,
        'status_tim_kurikulum' => true,
        'jabatan' => 'Anggota Tim Kurikulum',
    ]);

    return $user;
}
