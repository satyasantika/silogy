<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Filament\Resources\CplResource;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
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
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Policies\InputNilaiPolicy;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
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

it('pembuatan kelas mk hanya oleh admin dengan penugasan langsung di prodi', function () {
    $policy = new KelasMkPolicy;

    expect($policy->create($this->adminProdi))->toBeTrue()
        ->and($policy->create(User::where('username', 'timkur')->firstOrFail()))->toBeFalse()
        ->and($policy->create(User::where('username', 'adminfak')->firstOrFail()))->toBeFalse()
        ->and($policy->create(User::where('username', 'timkurfak')->firstOrFail()))->toBeFalse();
});

it('koordinator mk dapat menetapkan dosen pengampu pada kelas yang dikoordinasikannya', function () {
    $korma = User::where('username', 'korma')->firstOrFail();
    $dosen = User::where('username', 'dosen')->firstOrFail();
    $kelas = kelasDariMkUniv($this);
    $kelas->update(['koordinator_mk_id' => $korma->id]);

    $policy = new KelasMkPolicy;

    expect($policy->assignDosenPengampu($korma, $kelas->fresh()))->toBeTrue()
        ->and($policy->assignDosenPengampu($dosen, $kelas->fresh()))->toBeFalse();

    $this->actingAs($korma);

    Livewire::test(EditKelasMk::class, ['record' => $kelas->id])
        ->fillForm(['dosen_pengampu_id' => $dosen->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($kelas->fresh()->dosen_pengampu_id)->toBe($dosen->id);
});

it('dosen pengampu dapat mengimpor nim ke kelasnya sendiri namun tidak ke kelas lain', function () {
    $this->seed(MahasiswaSeeder::class);
    $dosen = User::where('username', 'dosen')->firstOrFail();
    $kelas = kelasDariMkUniv($this);
    $kelas->update(['dosen_pengampu_id' => $dosen->id]);

    $policy = new KelasMkPolicy;

    expect($policy->kelolaMahasiswa($dosen, $kelas->fresh()))->toBeTrue();

    $nim = Mahasiswa::query()->where('academic_unit_id', $this->prodi->id)->value('nim');

    $this->actingAs($dosen);

    Livewire::test(KelasMkMahasiswaRelationManager::class, [
        'ownerRecord' => $kelas->fresh(),
        'pageClass' => EditKelasMk::class,
    ])->callTableAction('bulkImport', data: ['rows' => $nim, 'mode_duplikat' => 'lewati']);

    expect(KelasMkMahasiswa::query()->where('kelas_mk_id', $kelas->id)->count())->toBe(1);

    // Kelas lain (bukan pengampunya) → tidak boleh.
    $kelasLain = KelasMk::query()->create([
        'mk_unit_id' => $kelas->mk_unit_id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'B',
    ]);

    expect($policy->kelolaMahasiswa($dosen, $kelasLain))->toBeFalse();
});

it('dosen baru dapat menilai setelah penugasan koordinator selesai', function () {
    $dosen = User::where('username', 'dosen')->firstOrFail();
    $kelas = kelasDariMkUniv($this);
    $kelas->update(['dosen_pengampu_id' => $dosen->id]);

    $policyNilai = new InputNilaiPolicy;

    // Belum ada komponen → belum boleh menilai.
    expect($kelas->fresh()->penugasanSelesai())->toBeFalse()
        ->and($policyNilai->inputNilai($dosen, $kelas->fresh()))->toBeFalse();

    // Susun penugasan lengkap: komponen 100% terpetakan ke Sub-CPMK.
    $this->seed(EvaluasiSeeder::class);
    $mk = $kelas->mkUnit->mk;
    $cpl = Cpl::factory()->forAcademicUnit($this->univ)->create();
    $bok = Bok::factory()->forAcademicUnit($this->univ)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $mk->id, 'kode' => 'CPMK-1', 'deskripsi' => 'Uji']);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);
    $sub = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id, 'semester_id' => $this->semester->id,
        'kode' => 'SUB-1', 'deskripsi' => 'Uji', 'bobot' => 100,
    ]);
    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id, 'semester_id' => $kelas->semester_id, 'evaluasi_id' => $evaluasi->id,
        'kode' => 'UTS', 'nama' => 'UTS', 'bobot' => 100,
    ]);

    // Komponen ada tapi belum terpetakan → tetap belum boleh.
    expect($kelas->fresh()->penugasanSelesai())->toBeFalse();

    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $sub->id, 'komponen_penilaian_id' => $komponen->id, 'bobot' => 100,
    ]);

    expect($kelas->fresh()->penugasanSelesai())->toBeTrue()
        ->and($policyNilai->inputNilai($dosen, $kelas->fresh()))->toBeTrue();
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

it('admin prodi dengan peran aktif tim kurikulum tetap melihat menu kelas mk', function () {
    $adminTim = User::query()->where('username', 'adminprodi')->firstOrFail();
    $adminTim->assignRole('Tim Kurikulum');

    $this->actingAs($adminTim);
    session()->put(ActiveRole::SESSION_KEY, 'Tim Kurikulum');
    $adminTim->unsetRelation('roles');

    expect($adminTim->hasRole('Admin'))->toBeFalse()
        ->and($adminTim->hasRole('Tim Kurikulum'))->toBeTrue()
        ->and(KelasMkResource::shouldRegisterNavigation())->toBeTrue()
        ->and(KelasMkResource::canAccess())->toBeTrue();
});

it('admin prodi melihat menu kelas mk tanpa prasyarat kelas', function () {
    $this->actingAs($this->adminProdi);

    expect(KelasMkResource::shouldRegisterNavigation())->toBeTrue()
        ->and(KelasMkResource::canAccess())->toBeTrue();

    $this->get('/dashboard')
        ->assertSuccessful()
        ->assertSee('Kelas MK', escape: false);
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
