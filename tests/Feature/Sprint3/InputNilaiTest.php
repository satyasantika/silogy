<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Policies\InputNilaiPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\MahasiswaSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @return array{
 *     kelas: KelasMk,
 *     kmm: KelasMkMahasiswa,
 *     skp: SubcpmkKomponenPenilaian
 * }
 */
function setupInputNilaiFixtures(User $dosen, string $kodeMkUnit = 'IF101', string $kodeKelas = 'A'): array
{
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create(['kode' => $kodeMkUnit]);

    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => $kodeKelas,
        'dosen_pengampu_id' => $dosen->id,
    ]);

    $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);
    $kmm = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelas->id,
        'mahasiswa_id' => $mahasiswa->id,
    ]);

    $cpmk = Cpmk::factory()->forMk($mk)->create();
    $cpl = Cpl::factory()->forAcademicUnit($prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($prodi)->create();
    $cplBok = CplBok::query()->create([
        'cpl_id' => $cpl->id,
        'bok_id' => $bok->id,
        'bobot' => 100,
    ]);
    $cplMk = CplMk::query()->create([
        'cpl_bok_id' => $cplBok->id,
        'mk_id' => $mk->id,
        'bobot' => 100,
    ]);
    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create(['kode' => 'SUB-01']);

    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
    $komponen = KomponenPenilaian::query()->create([
        'kelas_mk_id' => $kelas->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 100,
    ]);
    $skp = SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);

    return compact('kelas', 'kmm', 'skp');
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);
    $this->seed(MahasiswaSeeder::class);

    $this->dosen = User::where('username', 'dosen')->first();
    $this->dosenLain = User::factory()->create([
        'username' => 'dosenlain',
        'email' => 'dosenlain@silogy.test',
        'full_name' => 'Dosen Lain',
    ]);
    $this->dosenLain->assignRole('Dosen Pengampu');

    $this->korma = User::where('username', 'korma')->first();
});

it('lets dosen save nilai in matrix', function () {
    $this->actingAs($this->dosen);
    $fixtures = setupInputNilaiFixtures($this->dosen);

    Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->set('nilai', [
            $fixtures['kmm']->id => [
                $fixtures['skp']->id => '85.5',
            ],
        ])
        ->call('save')
        ->assertNotified();

    $nilai = NilaiMahasiswa::query()
        ->where('kelas_mk_mahasiswa_id', $fixtures['kmm']->id)
        ->where('subcpmk_komponenpenilaian_id', $fixtures['skp']->id)
        ->first();

    expect($nilai)->not->toBeNull()
        ->and((float) $nilai->nilai)->toBe(85.5);

    Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSet('nilai.'.$fixtures['kmm']->id.'.'.$fixtures['skp']->id, '85.50');
});

it('blocks dosen from inputting other dosen class', function () {
    $this->actingAs($this->dosen);
    $fixturesDosenLain = setupInputNilaiFixtures($this->dosenLain, 'IF102', 'B');
    $fixturesDosen = setupInputNilaiFixtures($this->dosen, 'IF201', 'A');

    $policy = app(InputNilaiPolicy::class);

    expect($policy->inputNilai($this->dosen, $fixturesDosenLain['kelas']))->toBeFalse()
        ->and($policy->inputNilai($this->dosen, $fixturesDosen['kelas']))->toBeTrue();

    Gate::forUser($this->dosen)->authorize('inputNilai', $fixturesDosen['kelas']);

    expect(fn () => Gate::forUser($this->dosen)->authorize('inputNilai', $fixturesDosenLain['kelas']))
        ->toThrow(AuthorizationException::class);
});

it('blocks role without input nilai permission', function () {
    $this->actingAs($this->korma);

    expect(InputNilai::canAccess())->toBeFalse()
        ->and(app(InputNilaiPolicy::class)->access($this->korma))->toBeFalse();

    Permission::findByName('input_nilai', 'web')->revokePermissionTo($this->dosen);
    $this->dosen->refresh();

    $this->actingAs($this->dosen);

    expect(InputNilai::canAccess())->toBeFalse();
});

it('applies pasted matrix via applyTempel', function () {
    $this->actingAs($this->dosen);
    $fixtures = setupInputNilaiFixtures($this->dosen);
    $fixtures['kmm']->load('mahasiswa');

    $paste = sprintf(
        "NIM\tNama\tUTS / SUB-01\n%s\t%s\t91",
        $fixtures['kmm']->mahasiswa->nim,
        $fixtures['kmm']->mahasiswa->nama,
    );

    Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->call('applyTempel', $paste)
        ->assertNotified()
        ->assertSet('nilai.'.$fixtures['kmm']->id.'.'.$fixtures['skp']->id, '91');
});
