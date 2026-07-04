<?php

use App\Models\User;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\ListBoks;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource\Pages\ListAcademicUnits;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Policies\AcademicUnitPolicy;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\ListKelasMks;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\EditKurikulum;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers\ProfilLulusanRelationManager;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Mahasiswa\Filament\Resources\MahasiswaResource\Pages\ListMahasiswas;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\ListCpmks;
use App\Modules\MK\Filament\Resources\MkResource\Pages\ListMks;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\ListSubcpmks;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
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
    $this->actingAs(User::where('username', 'superadmin')->firstOrFail());

    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
});

it('unit akademik terpakai tidak dapat dihapus, unit kosong dapat', function () {
    $superadmin = auth()->user();
    $policy = new AcademicUnitPolicy;

    // FKIP punya sub-unit → terlindungi.
    $fkip = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();

    expect($fkip->hasDependentRecords())->toBeTrue()
        ->and($policy->delete($superadmin, $fkip))->toBeFalse();

    $kosong = AcademicUnit::factory()->studyProgram($fkip)->create([
        'nama' => 'Prodi Kosong',
        'code' => 'KOSONG',
        'kode_pddikti' => null,
    ]);

    expect($kosong->hasDependentRecords())->toBeFalse()
        ->and($policy->delete($superadmin, $kosong))->toBeTrue();
});

it('impor unit akademik: baru dibuat, duplikat kode terdeteksi', function () {
    $rows = implode("\n", [
        'fakultas|FT2|Fakultas Teknik|UNSIL|FT|aktif',
        'prodi|PTI|Prodi Pendidikan Informatika|21||aktif',
        'fakultas|21|Duplikat FKIP|UNSIL||aktif',
    ]);

    Livewire::test(ListAcademicUnits::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati']);

    $ft = AcademicUnit::query()->where('code', 'FT2')->first();
    $pti = AcademicUnit::query()->where('code', 'PTI')->first();

    expect($ft)->not->toBeNull()
        ->and($ft->type)->toBe('faculty')
        ->and($ft->parent->code)->toBe('UNSIL')
        ->and($pti)->not->toBeNull()
        ->and($pti->parent->code)->toBe('21')
        ->and(AcademicUnit::query()->where('nama', 'Duplikat FKIP')->exists())->toBeFalse();
});

it('impor mahasiswa ke prodi terpilih dengan duplikat nim dilewati', function () {
    Mahasiswa::factory()->create(['nim' => '227000001', 'academic_unit_id' => $this->prodi->id]);

    $rows = "227000001\tMahasiswa Lama\t2022\tL\tlama@silogy.test\n"
        ."227000099\tMahasiswa Baru\t2022\tP\tbaru@silogy.test";

    Livewire::test(ListMahasiswas::class)
        ->callAction('bulkImport', [
            'rows' => $rows,
            'mode_duplikat' => 'lewati',
            'import_unit_id' => $this->prodi->id,
        ]);

    $baru = Mahasiswa::query()->where('nim', '227000099')->first();

    expect($baru)->not->toBeNull()
        ->and($baru->nama)->toBe('Mahasiswa Baru')
        ->and($baru->academic_unit_id)->toBe($this->prodi->id)
        ->and(Mahasiswa::query()->where('nim', '227000001')->value('nama'))->not->toBe('Mahasiswa Lama');
});

it('impor cpl per unit dengan validasi domain', function () {
    $rows = implode("\n", [
        'CPL-IMP-01|Mampu menerapkan pedagogik matematika|kognitif',
        'CPL-IMP-02|Domain salah|tidakvalid',
    ]);

    Livewire::test(ListCpls::class)
        ->callAction('bulkImport', [
            'rows' => $rows,
            'mode_duplikat' => 'lewati',
            'import_unit_id' => $this->prodi->id,
        ]);

    expect(Cpl::query()->where('kode', 'CPL-IMP-01')->where('academic_unit_id', $this->prodi->id)->exists())->toBeTrue()
        ->and(Cpl::query()->where('kode', 'CPL-IMP-02')->exists())->toBeFalse();
});

it('impor bok sekaligus memetakan ke cpl', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-MAP']);

    Livewire::test(ListBoks::class)
        ->callAction('bulkImport', [
            'rows' => 'BOK-IMP-01|Aljabar Dasar|Bahan kajian aljabar|CPL-MAP',
            'mode_duplikat' => 'lewati',
            'import_unit_id' => $this->prodi->id,
        ]);

    $bok = Bok::query()->where('kode', 'BOK-IMP-01')->first();

    expect($bok)->not->toBeNull()
        ->and(CplBok::query()->where('cpl_id', $cpl->id)->where('bok_id', $bok->id)->exists())->toBeTrue();
});

it('impor mk dengan sks, jenis, dan koordinator', function () {
    Livewire::test(ListMks::class)
        ->callAction('bulkImport', [
            'rows' => "Kalkulus Impor\t3\t1\t0\twajib\tkorma",
            'mode_duplikat' => 'lewati',
            'import_unit_id' => $this->prodi->id,
        ]);

    $mk = Mk::query()->where('nama', 'Kalkulus Impor')->first();
    $korma = User::query()->where('username', 'korma')->firstOrFail();

    expect($mk)->not->toBeNull()
        ->and($mk->sks)->toBe(4)
        ->and($mk->jenis)->toBe('wajib')
        ->and($mk->koordinator_mk_id)->toBe($korma->id);
});

it('impor cpmk pada mk terpilih', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);

    Livewire::test(ListCpmks::class)
        ->callAction('bulkImport', [
            'rows' => "CPMK-IMP-01|Mahasiswa memahami konsep dasar\nCPMK-IMP-02|Mahasiswa mampu menganalisis",
            'mode_duplikat' => 'lewati',
            'import_mk_id' => $mk->id,
        ]);

    expect(Cpmk::query()->where('mk_id', $mk->id)->count())->toBe(2);
});

it('impor subcpmk pada cpmk dan semester terpilih', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $mk->id, 'kode' => 'CPMK-01', 'deskripsi' => 'Uji']);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    Livewire::test(ListSubcpmks::class)
        ->callAction('bulkImport', [
            'rows' => 'SUB-IMP-A|Menjelaskan definisi|50|Indikator A',
            'mode_duplikat' => 'lewati',
            'import_mk_cpmk_id' => $mkCpmk->id,
            'import_semester_id' => $semester->id,
        ]);

    $sub = Subcpmk::query()->where('kode', 'SUB-IMP-A')->first();

    expect($sub)->not->toBeNull()
        ->and($sub->mk_cpmk_id)->toBe($mkCpmk->id)
        ->and($sub->bobot)->toBe(50.0);
});

it('impor kelas mk dengan koordinator default dari mk', function () {
    $korma = User::query()->where('username', 'korma')->firstOrFail();
    $dosen = User::query()->where('username', 'dosen')->firstOrFail();
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $korma->id,
    ]);
    MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create(['kode' => 'IMP101']);
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    Livewire::test(ListKelasMks::class)
        ->callAction('bulkImport', [
            'rows' => "IMP101|A|dosen||40\nIMP101|B|dosen||40",
            'mode_duplikat' => 'lewati',
            'import_semester_id' => $semester->id,
        ]);

    $kelasA = KelasMk::query()->where('kode_kelas', 'A')->whereHas('mkUnit', fn ($q) => $q->where('kode', 'IMP101'))->first();

    expect(KelasMk::query()->whereHas('mkUnit', fn ($q) => $q->where('kode', 'IMP101'))->count())->toBe(2)
        ->and($kelasA->dosen_pengampu_id)->toBe($dosen->id)
        ->and($kelasA->koordinator_mk_id)->toBe($korma->id);
});

it('impor profil lulusan pada kurikulum prodi', function () {
    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Impor',
        'tahun' => 2026,
    ]);

    Livewire::test(ProfilLulusanRelationManager::class, [
        'ownerRecord' => $kurikulum,
        'pageClass' => EditKurikulum::class,
    ])
        ->callTableAction('bulkImport', data: [
            'rows' => 'PL-IMP-1|Pendidik|Menjadi pendidik matematika profesional|1',
            'mode_duplikat' => 'lewati',
        ]);

    $profil = ProfilLulusan::query()->where('kode', 'PL-IMP-1')->first();

    expect($profil)->not->toBeNull()
        ->and($profil->kurikulum_id)->toBe($kurikulum->id)
        ->and($profil->deskripsi)->toBe('Menjadi pendidik matematika profesional');
});

it('mode timpa memperbarui data duplikat pada impor cpl', function () {
    Cpl::factory()->forAcademicUnit($this->prodi)->create([
        'kode' => 'CPL-TIMPA',
        'deskripsi' => 'Deskripsi lama',
    ]);

    Livewire::test(ListCpls::class)
        ->callAction('bulkImport', [
            'rows' => 'CPL-TIMPA|Deskripsi baru hasil timpa|afektif',
            'mode_duplikat' => 'timpa',
            'import_unit_id' => $this->prodi->id,
        ]);

    $cpl = Cpl::query()->where('kode', 'CPL-TIMPA')->firstOrFail();

    expect($cpl->deskripsi)->toBe('Deskripsi baru hasil timpa')
        ->and($cpl->domain)->toBe('afektif')
        ->and(Cpl::query()->where('kode', 'CPL-TIMPA')->count())->toBe(1);
});
