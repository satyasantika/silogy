<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkResource\Pages\EditMk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
});

function buatTimkurProdiUntukReset(AcademicUnit $prodi): User
{
    $user = User::create([
        'full_name' => 'Timkur Prodi Uji Reset',
        'username' => 'timkurreset'.Str::random(4),
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

it('tim kurikulum dapat mereset cpmk subcpmk dan asesmen sebuah mk', function () {
    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Reset CPMK',
        'kode' => 'RST01',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $mk = Mk::factory()->forKurikulum($kurikulum)->create();
    $cpl = Cpl::factory()->forKurikulum($kurikulum)->create();
    $bok = Bok::factory()->forKurikulum($kurikulum)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);

    $cpmk = Cpmk::query()->create(['mk_id' => $mk->id, 'kode' => 'CPMK01', 'deskripsi' => 'Deskripsi CPMK.']);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);
    $subcpmk = Subcpmk::query()->create(['mk_cpmk_id' => $mkCpmk->id, 'kode' => 'SUB01', 'deskripsi' => 'Deskripsi Sub-CPMK.']);

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $evaluasi = Evaluasi::query()->firstOrFail();
    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 100,
    ]);

    $timkur = buatTimkurProdiUntukReset($this->prodi);
    $this->actingAs($timkur);
    KurikulumTerpilih::set($kurikulum->id);

    Livewire::test(EditMk::class, ['record' => $mk->id])
        ->callAction('resetCpmkSubcpmkAsesmen');

    $this->assertDatabaseMissing('cpmk', ['id' => $cpmk->id]);
    $this->assertDatabaseMissing('mk_cpmk', ['id' => $mkCpmk->id]);
    $this->assertDatabaseMissing('subcpmk', ['id' => $subcpmk->id]);
    $this->assertDatabaseMissing('komponen_penilaian', ['id' => $komponen->id]);

    // MK, CPL, BoK, dan pemetaan CPL-MK TIDAK ikut terhapus.
    $this->assertDatabaseHas('mk', ['id' => $mk->id]);
    $this->assertDatabaseHas('cpl_mk', ['id' => $cplMk->id]);
    $this->assertDatabaseHas('cpl', ['id' => $cpl->id]);
    $this->assertDatabaseHas('bok', ['id' => $bok->id]);
});

it('super admin dan tim kurikulum unit lain tidak bisa memicu reset cpmk', function () {
    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Otorisasi',
        'kode' => 'RST02',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $mk = Mk::factory()->forKurikulum($kurikulum)->create();

    $superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
    expect(Gate::forUser($superAdmin)->denies('update', $mk))->toBeTrue();

    $prodiLain = AcademicUnit::query()->where('type', 'study_program')->where('id', '!=', $this->prodi->id)->first();

    if ($prodiLain) {
        $timkurLain = buatTimkurProdiUntukReset($prodiLain);
        expect(Gate::forUser($timkurLain)->denies('update', $mk))->toBeTrue();
    }
});
