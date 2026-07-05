<?php

use App\Models\User;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\ListBoks;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\CPL\Models\CplProfilLulusan;
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
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\ListKomponenPenilaians;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
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
    $this->seed(EvaluasiSeeder::class);
    $this->actingAs(User::where('username', 'superadmin')->firstOrFail());

    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();

    $this->kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Impor',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->kurikulumFak = Kurikulum::query()->create([
        'academic_unit_id' => $this->fakultas->id,
        'nama' => 'Kurikulum Fakultas Uji Impor',
        'tahun' => 2026,
        'is_active' => true,
    ]);
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
        '|CPL-IMP-01|Mampu menerapkan pedagogik matematika|kognitif',
        '|CPL-IMP-02|Domain salah|tidakvalid',
    ]);

    Livewire::test(ListCpls::class)
        ->callAction('bulkImport', [
            'rows' => $rows,
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    expect(Cpl::query()->where('kode', 'CPL-IMP-01')->where('academic_unit_id', $this->prodi->id)->exists())->toBeTrue()
        ->and(Cpl::query()->where('kode', 'CPL-IMP-02')->exists())->toBeFalse();
});

it('impor cpl prodi memetakan ke beberapa profil lulusan sekaligus', function () {
    ProfilLulusan::query()->create([
        'kurikulum_id' => $this->kurikulumProdi->id,
        'kode' => 'PL-1',
        'nama' => 'Pendidik',
        'deskripsi' => 'Profil pendidik',
    ]);

    ProfilLulusan::query()->create([
        'kurikulum_id' => $this->kurikulumProdi->id,
        'kode' => 'PL-2',
        'nama' => 'Peneliti',
        'deskripsi' => 'Profil peneliti',
    ]);

    Livewire::test(ListCpls::class)
        ->callAction('bulkImport', [
            'rows' => 'Pendidik;Peneliti|CPL-PROFIL-01|Mampu pedagogik|kognitif',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    $cpl = Cpl::query()->where('kode', 'CPL-PROFIL-01')->firstOrFail();

    expect(CplProfilLulusan::query()->where('cpl_id', $cpl->id)->count())->toBe(2);
});

it('impor cpl prodi menolak profil lulusan yang tidak ada', function () {
    Livewire::test(ListCpls::class)
        ->callAction('bulkImport', [
            'rows' => 'Profil Tidak Ada|CPL-INVALID-PROFIL|Deskripsi CPL|kognitif',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    expect(Cpl::query()->where('kode', 'CPL-INVALID-PROFIL')->exists())->toBeFalse();
});

it('impor cpl fakultas tanpa kolom profil lulusan', function () {
    Livewire::test(ListCpls::class)
        ->callAction('bulkImport', [
            'rows' => 'CPL-FAK-01|CPL tingkat fakultas|kognitif',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumFak->id,
        ]);

    expect(Cpl::query()->where('kode', 'CPL-FAK-01')->where('academic_unit_id', $this->fakultas->id)->exists())->toBeTrue();
});

it('impor cpl fakultas menolak baris dengan kolom profil lulusan', function () {
    Livewire::test(ListCpls::class)
        ->callAction('bulkImport', [
            'rows' => 'Pendidik|CPL-FAK-PROFIL|Deskripsi|kognitif',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumFak->id,
        ]);

    expect(Cpl::query()->where('kode', 'CPL-FAK-PROFIL')->exists())->toBeFalse();
});

it('impor bok sekaligus memetakan ke cpl', function () {
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-MAP']);

    Livewire::test(ListBoks::class)
        ->callAction('bulkImport', [
            'rows' => 'BOK-IMP-01|Aljabar Dasar|Bahan kajian aljabar|CPL-MAP',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    $bok = Bok::query()->where('kode', 'BOK-IMP-01')->first();

    expect($bok)->not->toBeNull()
        ->and(CplBok::query()->where('cpl_id', $cpl->id)->where('bok_id', $bok->id)->exists())->toBeTrue();
});

it('impor bok dapat memetakan ke beberapa cpl sekaligus', function () {
    $cpl1 = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-M1']);
    $cpl2 = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-M2']);

    Livewire::test(ListBoks::class)
        ->callAction('bulkImport', [
            'rows' => 'BOK-MULTI|Statistik|BoK statistik|CPL-M1;CPL-M2',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    $bok = Bok::query()->where('kode', 'BOK-MULTI')->firstOrFail();

    expect(CplBok::query()->where('bok_id', $bok->id)->count())->toBe(2)
        ->and(CplBok::query()->where('cpl_id', $cpl1->id)->where('bok_id', $bok->id)->exists())->toBeTrue()
        ->and(CplBok::query()->where('cpl_id', $cpl2->id)->where('bok_id', $bok->id)->exists())->toBeTrue();
});

it('impor bok menolak kode cpl tidak valid dalam daftar titik koma', function () {
    Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-ADA']);

    Livewire::test(ListBoks::class)
        ->callAction('bulkImport', [
            'rows' => 'BOK-INVALID|Geometri||CPL-ADA;CPL-TIDAK-ADA',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    expect(Bok::query()->where('kode', 'BOK-INVALID')->exists())->toBeFalse();
});

it('impor mk dengan sks, jenis, dan koordinator', function () {
    $korma = User::query()->where('username', 'korma')->firstOrFail();

    Livewire::test(ListMks::class)
        ->callAction('bulkImport', [
            'rows' => "Kalkulus Impor\t3\t1\t0\twajib\t\t{$korma->nidn}",
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    $mk = Mk::query()->where('nama', 'Kalkulus Impor')->first();

    expect($mk)->not->toBeNull()
        ->and($mk->sks)->toBe(4)
        ->and($mk->jenis)->toBe('wajib')
        ->and($mk->koordinator_mk_id)->toBe($korma->id);
});

it('impor mk menganggap sks praktik dan lapangan kosong sebagai nol', function () {
    Livewire::test(ListMks::class)
        ->callAction('bulkImport', [
            'rows' => "Teori Saja\t3\t\t\twajib",
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    $mk = Mk::query()->where('nama', 'Teori Saja')->firstOrFail();

    expect($mk->sks_teori)->toBe(3)
        ->and($mk->sks_praktik)->toBe(0)
        ->and($mk->sks_lapangan)->toBe(0)
        ->and($mk->sks)->toBe(3);
});

it('impor mk memetakan ke beberapa bahan kajian sekaligus', function () {
    $cpl1 = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-MK1']);
    $cpl2 = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-MK2']);
    $bok1 = Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-MK1']);
    $bok2 = Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-MK2']);
    CplBok::query()->create(['cpl_id' => $cpl1->id, 'bok_id' => $bok1->id, 'bobot' => 100]);
    CplBok::query()->create(['cpl_id' => $cpl2->id, 'bok_id' => $bok2->id, 'bobot' => 100]);

    Livewire::test(ListMks::class)
        ->callAction('bulkImport', [
            'rows' => 'MK BoK Multi|3|0|0|wajib|BOK-MK1;BOK-MK2',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    $mk = Mk::query()->where('nama', 'MK BoK Multi')->firstOrFail();

    expect(CplMk::query()->where('mk_id', $mk->id)->count())->toBe(2);
});

it('impor mk menolak kode bahan kajian yang belum dipetakan ke cpl', function () {
    Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-TANPA-CPL']);

    Livewire::test(ListMks::class)
        ->callAction('bulkImport', [
            'rows' => 'MK Invalid BoK|3|0|0|wajib|BOK-TANPA-CPL',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    expect(Mk::query()->where('nama', 'MK Invalid BoK')->exists())->toBeFalse();
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

it('impor cpmk dengan kode cpl terpetakan langsung memetakan ke cpl', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-IMP']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);

    Livewire::test(ListCpmks::class)
        ->callAction('bulkImport', [
            'rows' => 'CPMK-CPL|Mahasiswa memetakan CPL|CPL-IMP',
            'mode_duplikat' => 'lewati',
            'import_mk_id' => $mk->id,
        ]);

    $cpmk = Cpmk::query()->where('kode', 'CPMK-CPL')->firstOrFail();

    expect(MkCpmk::query()
        ->where('cpmk_id', $cpmk->id)
        ->where('cpl_mk_id', $cplMk->id)
        ->exists())->toBeTrue();
});

it('impor cpmk menolak kode cpl bila cpl mk belum ada', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-TANPA-MK']);

    Livewire::test(ListCpmks::class)
        ->callAction('bulkImport', [
            'rows' => 'CPMK-X|Deskripsi|CPL-TANPA-MK',
            'mode_duplikat' => 'lewati',
            'import_mk_id' => $mk->id,
        ]);

    expect(Cpmk::query()->where('kode', 'CPMK-X')->exists())->toBeFalse();
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
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create();
    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);

    Livewire::test(ListSubcpmks::class)
        ->callAction('bulkImport', [
            'rows' => 'CPMK-01|SUB-IMP-A|Menjelaskan definisi||Indikator A||50',
            'mode_duplikat' => 'lewati',
            'import_mk_id' => $mk->id,
            'import_semester_id' => $semester->id,
        ]);

    $sub = Subcpmk::query()->where('kode', 'SUB-IMP-A')->first();

    expect($sub)->not->toBeNull()
        ->and($sub->mk_cpmk_id)->toBe($mkCpmk->id)
        ->and($sub->bobot)->toBe(50.0);
});

it('impor subcpmk dengan kompetensi bloom dan evaluasi', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $mk->id, 'kode' => 'CPMK-01', 'deskripsi' => 'Uji']);
    MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create();
    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);

    Livewire::test(ListSubcpmks::class)
        ->callAction('bulkImport', [
            'rows' => 'CPMK-01|SUB-BLOOM|Menjelaskan konsep|C3,A2,P2||UTS dan kuis|40',
            'mode_duplikat' => 'lewati',
            'import_mk_id' => $mk->id,
            'import_semester_id' => $semester->id,
        ]);

    $sub = Subcpmk::query()->where('kode', 'SUB-BLOOM')->firstOrFail();

    expect($sub->bloom_kognitif)->toBe('C3')
        ->and($sub->bloom_afektif)->toBe('A2')
        ->and($sub->bloom_psikomotorik)->toBe('P2')
        ->and($sub->evaluasi)->toBe('UTS dan kuis');
});

it('impor subcpmk menolak format kompetensi salah', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $mk->id, 'kode' => 'CPMK-01', 'deskripsi' => 'Uji']);
    MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create();
    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);

    Livewire::test(ListSubcpmks::class)
        ->callAction('bulkImport', [
            'rows' => 'CPMK-01|SUB-INVALID|Deskripsi|X9|||',
            'mode_duplikat' => 'lewati',
            'import_mk_id' => $mk->id,
            'import_semester_id' => $semester->id,
        ]);

    expect(Subcpmk::query()->where('kode', 'SUB-INVALID')->exists())->toBeFalse();
});

it('impor subcpmk berhasil meski belum ada kelas mk', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $mk->id, 'kode' => 'CPMK-01', 'deskripsi' => 'Uji']);
    MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    Livewire::test(ListSubcpmks::class)
        ->callAction('bulkImport', [
            'rows' => 'CPMK-01|SUB-TANPA-KELAS|Deskripsi||||50',
            'mode_duplikat' => 'lewati',
            'import_mk_id' => $mk->id,
            'import_semester_id' => $semester->id,
        ]);

    expect(Subcpmk::query()->where('kode', 'SUB-TANPA-KELAS')->exists())->toBeTrue();
});

it('impor asesmen dengan pemetaan subcpmk dan bobot merata', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $mk->id, 'kode' => 'CPMK-01', 'deskripsi' => 'Uji']);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);
    $sub1 = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id,
        'semester_id' => $semester->id,
        'kode' => 'SubCPMK01.1',
        'deskripsi' => 'Sub 1',
    ]);
    $sub2 = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id,
        'semester_id' => $semester->id,
        'kode' => 'SubCPMK01.2',
        'deskripsi' => 'Sub 2',
    ]);

    Livewire::test(ListKomponenPenilaians::class)
        ->callAction('bulkImport', [
            'rows' => implode("\n", [
                'Asesmen01|Kuis Konseptual dan Ringkasan Tertulis Terstruktur|8|Quiz|SubCPMK01.1',
                'Asesmen01|Kuis Konseptual dan Ringkasan Tertulis Terstruktur|8|Quiz|SubCPMK01.2',
            ]),
            'mode_duplikat' => 'lewati',
            'import_mk_id' => $mk->id,
            'import_semester_id' => $semester->id,
        ]);

    $komponen = KomponenPenilaian::query()->where('kode', 'Asesmen01')->firstOrFail();
    $evaluasi = Evaluasi::query()->where('kode', 'quiz')->firstOrFail();

    expect($komponen->nama)->toBe('Kuis Konseptual dan Ringkasan Tertulis Terstruktur')
        ->and((float) $komponen->bobot)->toBe(8.0)
        ->and($komponen->evaluasi_id)->toBe($evaluasi->id);

    $pivots = SubcpmkKomponenPenilaian::query()
        ->where('komponen_penilaian_id', $komponen->id)
        ->orderBy('subcpmk_id')
        ->get();

    expect($pivots)->toHaveCount(2)
        ->and($pivots->pluck('subcpmk_id')->all())->toContain($sub1->id, $sub2->id)
        ->and((float) $pivots->first()->bobot)->toBe(50.0)
        ->and((float) $pivots->last()->bobot)->toBe(50.0);
});

it('impor asesmen menolak evaluasi tidak valid', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);

    Livewire::test(ListKomponenPenilaians::class)
        ->callAction('bulkImport', [
            'rows' => 'AsesmenX|Tugas X|10|TidakValid|',
            'mode_duplikat' => 'lewati',
            'import_mk_id' => $mk->id,
            'import_semester_id' => $semester->id,
        ]);

    expect(KomponenPenilaian::query()->where('kode', 'AsesmenX')->exists())->toBeFalse();
});

it('impor asesmen menerapkan ke semua kelas mk pada mk dan semester', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $kelasA = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);
    $kelasB = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'B',
    ]);

    Livewire::test(ListKomponenPenilaians::class)
        ->callAction('bulkImport', [
            'rows' => 'Asesmen02|UTS Teori|42|Ujian Tengah Semester|',
            'mode_duplikat' => 'lewati',
            'import_mk_id' => $mk->id,
            'import_semester_id' => $semester->id,
        ]);

    expect(KomponenPenilaian::query()->where('kode', 'Asesmen02')->count())->toBe(2)
        ->and(KomponenPenilaian::query()->where('kelas_mk_id', $kelasA->id)->where('kode', 'Asesmen02')->exists())->toBeTrue()
        ->and(KomponenPenilaian::query()->where('kelas_mk_id', $kelasB->id)->where('kode', 'Asesmen02')->exists())->toBeTrue();
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
            'rows' => "IMP101|A|dosen\nIMP101|B|dosen",
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

    $indikator = '(1) Indikator satu (2) Indikator dua';

    Livewire::test(ProfilLulusanRelationManager::class, [
        'ownerRecord' => $kurikulum,
        'pageClass' => EditKurikulum::class,
    ])
        ->callTableAction('bulkImport', data: [
            'rows' => "1|Pendidik|Menjadi pendidik matematika profesional|{$indikator}",
            'mode_duplikat' => 'lewati',
        ]);

    $profil = ProfilLulusan::query()->where('nama', 'Pendidik')->first();

    expect($profil)->not->toBeNull()
        ->and($profil->kurikulum_id)->toBe($kurikulum->id)
        ->and($profil->kode)->toBe('PL-1')
        ->and($profil->deskripsi)->toBe('Menjadi pendidik matematika profesional')
        ->and($profil->indikators()->count())->toBe(2);
});

it('impor profil lulusan mewajibkan nama dan mendeteksi jumlah indikator', function () {
    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Impor Opsional',
        'tahun' => 2026,
    ]);

    $empatIndikator = '(1) Satu (2) Dua (3) Tiga (4) Empat';

    Livewire::test(ProfilLulusanRelationManager::class, [
        'ownerRecord' => $kurikulum,
        'pageClass' => EditKurikulum::class,
    ])
        ->callTableAction('bulkImport', data: [
            'rows' => "2|Peneliti||{$empatIndikator}\n3||Deskripsi tanpa nama|",
            'mode_duplikat' => 'lewati',
        ]);

    $profil = ProfilLulusan::query()->where('nama', 'Peneliti')->first();
    $invalid = ProfilLulusan::query()->where('deskripsi', 'Deskripsi tanpa nama')->first();

    expect($profil)->not->toBeNull()
        ->and($profil->deskripsi)->toBe('')
        ->and($profil->indikators()->count())->toBe(4)
        ->and($invalid)->toBeNull();
});

it('mode timpa memperbarui data duplikat pada impor cpl', function () {
    Cpl::factory()->forAcademicUnit($this->prodi)->create([
        'kode' => 'CPL-TIMPA',
        'deskripsi' => 'Deskripsi lama',
    ]);

    Livewire::test(ListCpls::class)
        ->callAction('bulkImport', [
            'rows' => '|CPL-TIMPA|Deskripsi baru hasil timpa|afektif',
            'mode_duplikat' => 'timpa',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    $cpl = Cpl::query()->where('kode', 'CPL-TIMPA')->firstOrFail();

    expect($cpl->deskripsi)->toBe('Deskripsi baru hasil timpa')
        ->and($cpl->domain)->toBe('afektif')
        ->and(Cpl::query()->where('kode', 'CPL-TIMPA')->count())->toBe(1);
});
