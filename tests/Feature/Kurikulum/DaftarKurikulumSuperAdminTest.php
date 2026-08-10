<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Filament\Pages\DaftarKurikulumSuperAdmin;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
    $this->superAdmin = User::query()->where('username', 'superadmin')->firstOrFail();
});

/**
 * Bangun satu pohon data lengkap di bawah $kurikulum: ProfilLulusan+Indikator,
 * Cpl, Bok, CplBok, Mk (dengan koordinator ber-role Koordinator Mata Kuliah),
 * MkUnit, CplMk, Cpmk, MkCpmk, Subcpmk, KelasMk, KomponenPenilaian,
 * SubcpmkKomponenPenilaian, KelasMkMahasiswa, NilaiMahasiswa.
 *
 * @return array<string, mixed>
 */
function bangunPohonKurikulum(Kurikulum $kurikulum, AcademicUnit $prodi): array
{
    $profil = ProfilLulusan::query()->create([
        'id' => (string) Str::uuid(),
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL01',
        'nama' => 'Profil Lulusan',
        'deskripsi' => 'Deskripsi profil lulusan.',
    ]);
    $indikator = ProfilIndikator::query()->create([
        'id' => (string) Str::uuid(),
        'profil_id' => $profil->id,
        'nama' => 'Indikator',
        'deskripsi' => 'Deskripsi indikator.',
    ]);

    $cpl = Cpl::factory()->forKurikulum($kurikulum)->create();
    $cplProfilId = (string) Str::uuid();
    DB::table('cpl_profil_lulusan')->insert([
        'id' => $cplProfilId,
        'cpl_id' => $cpl->id,
        'profil_lulusan_id' => $profil->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $bok = Bok::factory()->forKurikulum($kurikulum)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);

    $koordinator = User::create([
        'full_name' => 'Koordinator Uji '.Str::random(4),
        'username' => 'korma'.Str::random(6),
        'email' => Str::random(8).'@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);
    $koordinator->forceFill(['email_verified_at' => now()])->save();
    $koordinator->assignRole(Role::where('name', 'Koordinator Mata Kuliah')->firstOrFail());

    $mk = Mk::factory()->forKurikulum($kurikulum)->create(['koordinator_mk_id' => $koordinator->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forKurikulum($kurikulum)->create();
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);

    $cpmk = Cpmk::query()->create(['mk_id' => $mk->id, 'kode' => 'CPMK01', 'deskripsi' => 'Deskripsi CPMK.']);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);
    $subcpmk = Subcpmk::query()->create(['mk_cpmk_id' => $mkCpmk->id, 'kode' => 'SUB01', 'deskripsi' => 'Deskripsi Sub-CPMK.']);

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $kelasMk = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);

    $evaluasi = Evaluasi::query()->firstOrFail();
    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 100,
    ]);
    $skp = SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'semester_id' => $semester->id,
        'bobot' => 100,
    ]);

    $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);
    $kelasMkMahasiswa = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasMk->id,
        'mahasiswa_id' => $mahasiswa->id,
    ]);
    $nilai = NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $skp->id,
        'kelas_mk_mahasiswa_id' => $kelasMkMahasiswa->id,
        'nilai' => 80,
    ]);

    return compact(
        'profil', 'indikator', 'cpl', 'bok', 'cplBok', 'mk', 'mkUnit', 'cplMk',
        'cpmk', 'mkCpmk', 'subcpmk', 'kelasMk', 'komponen', 'skp',
        'kelasMkMahasiswa', 'nilai', 'koordinator',
    );
}

function buatKurikulumUntuk(AcademicUnit $unit, string $nama): Kurikulum
{
    return Kurikulum::query()->create([
        'academic_unit_id' => $unit->id,
        'nama' => $nama,
        'kode' => Str::upper(Str::random(5)),
        'tahun' => 2026,
        'is_active' => true,
    ]);
}

it('super admin melihat semua kurikulum lintas unit dan hanya aksi reset yang tersedia', function () {
    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $kurikulumA = buatKurikulumUntuk($this->prodi, 'Kurikulum Prodi A');
    $kurikulumB = buatKurikulumUntuk($univ, 'Kurikulum Universitas B');

    $this->actingAs($this->superAdmin);

    Livewire::test(DaftarKurikulumSuperAdmin::class)
        ->assertSee($kurikulumA->nama)
        ->assertSee($kurikulumB->nama)
        ->assertTableActionExists('resetKurikulum')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('create')
        ->assertTableActionDoesNotExist('kerjakan');
});

it('reset kurikulum menghapus seluruh data turunan tapi menyisakan baris kurikulum', function () {
    $kurikulumA = buatKurikulumUntuk($this->prodi, 'Kurikulum Direset');
    $pohonA = bangunPohonKurikulum($kurikulumA, $this->prodi);

    $prodiLain = AcademicUnit::query()->where('type', 'study_program')->where('id', '!=', $this->prodi->id)->first()
        ?? $this->prodi;
    $kurikulumB = buatKurikulumUntuk($prodiLain, 'Kurikulum Tidak Disentuh');
    $pohonB = bangunPohonKurikulum($kurikulumB, $prodiLain);

    $this->actingAs($this->superAdmin);

    Livewire::test(DaftarKurikulumSuperAdmin::class)
        ->callTableAction('resetKurikulum', $kurikulumA);

    // Baris kurikulum A tetap ada.
    $this->assertDatabaseHas('kurikulum', ['id' => $kurikulumA->id]);

    // Semua turunan A hilang.
    $this->assertDatabaseMissing('profil_lulusan', ['id' => $pohonA['profil']->id]);
    $this->assertDatabaseMissing('profil_indikators', ['id' => $pohonA['indikator']->id]);
    $this->assertDatabaseMissing('cpl', ['id' => $pohonA['cpl']->id]);
    $this->assertDatabaseMissing('bok', ['id' => $pohonA['bok']->id]);
    $this->assertDatabaseMissing('cpl_bok', ['id' => $pohonA['cplBok']->id]);
    $this->assertDatabaseMissing('mk', ['id' => $pohonA['mk']->id]);
    $this->assertDatabaseMissing('mk_units', ['id' => $pohonA['mkUnit']->id]);
    $this->assertDatabaseMissing('cpl_mk', ['id' => $pohonA['cplMk']->id]);
    $this->assertDatabaseMissing('cpmk', ['id' => $pohonA['cpmk']->id]);
    $this->assertDatabaseMissing('mk_cpmk', ['id' => $pohonA['mkCpmk']->id]);
    $this->assertDatabaseMissing('subcpmk', ['id' => $pohonA['subcpmk']->id]);
    $this->assertDatabaseMissing('kelas_mk', ['id' => $pohonA['kelasMk']->id]);
    $this->assertDatabaseMissing('komponen_penilaian', ['id' => $pohonA['komponen']->id]);
    $this->assertDatabaseMissing('subcpmk_komponenpenilaian', ['id' => $pohonA['skp']->id]);
    $this->assertDatabaseMissing('kelas_mk_mahasiswa', ['id' => $pohonA['kelasMkMahasiswa']->id]);
    $this->assertDatabaseMissing('nilai_mahasiswas', ['id' => $pohonA['nilai']->id]);

    // Role Koordinator Mata Kuliah tercabut — bukti mks()->each->delete() dipakai.
    expect($pohonA['koordinator']->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeFalse();

    // Semua turunan B utuh.
    $this->assertDatabaseHas('profil_lulusan', ['id' => $pohonB['profil']->id]);
    $this->assertDatabaseHas('cpl', ['id' => $pohonB['cpl']->id]);
    $this->assertDatabaseHas('bok', ['id' => $pohonB['bok']->id]);
    $this->assertDatabaseHas('mk', ['id' => $pohonB['mk']->id]);
    $this->assertDatabaseHas('mk_units', ['id' => $pohonB['mkUnit']->id]);
    $this->assertDatabaseHas('cpmk', ['id' => $pohonB['cpmk']->id]);
    $this->assertDatabaseHas('subcpmk', ['id' => $pohonB['subcpmk']->id]);
    $this->assertDatabaseHas('komponen_penilaian', ['id' => $pohonB['komponen']->id]);
    expect($pohonB['koordinator']->fresh()->hasRole('Koordinator Mata Kuliah'))->toBeTrue();
});

it('mkunit lintas kurikulum ancestor ikut terhapus saat reset', function () {
    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $kurikulumUniv = buatKurikulumUntuk($univ, 'Kurikulum Universitas Sumber');
    $kurikulumProdi = buatKurikulumUntuk($this->prodi, 'Kurikulum Prodi Adaptasi');

    // MK "diadaptasi" dari universitas: mk.kurikulum_id = univ, tapi
    // mk_units.kurikulum_id = prodi (unit penawaran).
    $mkUniv = Mk::factory()->forKurikulum($kurikulumUniv)->create();
    $mkUnitAdaptasi = MkUnit::factory()->forMk($mkUniv)->forKurikulum($kurikulumProdi)->create();

    $this->actingAs($this->superAdmin);

    Livewire::test(DaftarKurikulumSuperAdmin::class)
        ->callTableAction('resetKurikulum', $kurikulumProdi);

    $this->assertDatabaseMissing('mk_units', ['id' => $mkUnitAdaptasi->id]);
    // MK sumber (milik kurikulum lain) tidak ikut terhapus.
    $this->assertDatabaseHas('mk', ['id' => $mkUniv->id]);
});

it('role selain super admin tidak bisa mengakses atau memicu reset kurikulum', function () {
    $kurikulum = buatKurikulumUntuk($this->prodi, 'Kurikulum Uji Otorisasi');
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();

    $this->actingAs($timkur);

    expect(DaftarKurikulumSuperAdmin::canAccess())->toBeFalse();

    $this->assertDatabaseHas('kurikulum', ['id' => $kurikulum->id]);
});
