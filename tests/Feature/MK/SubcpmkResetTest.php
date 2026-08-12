<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\ListSubcpmks;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Support\MkTerpilih;
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

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Reset SubCPMK',
        'kode' => 'RSTSC',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create(['koordinator_mk_id' => $this->korma->id]);

    $cpl = Cpl::factory()->forKurikulum($this->kurikulum)->create();
    $bok = Bok::factory()->forKurikulum($this->kurikulum)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $this->mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $this->mk->id, 'kode' => 'CPMK01', 'deskripsi' => 'Deskripsi.']);
    $this->mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);

    $this->actingAs($this->korma);
    KurikulumTerpilih::set($this->kurikulum->id);
    MkTerpilih::set($this->mk->id);
    SemesterTerpilih::set($this->mk->id, $this->semester->id);
});

it('tombol buat tidak lagi ada, impor massal selalu tampil', function () {
    Livewire::test(ListSubcpmks::class)
        ->assertActionDoesNotExist('create')
        ->assertActionExists('bulkImport');
});

it('tombol reset aktif saat belum ada subcpmk yang dipetakan ke asesmen', function () {
    Subcpmk::query()->create(['mk_cpmk_id' => $this->mkCpmk->id, 'semester_id' => $this->semester->id, 'kode' => 'SUB01', 'deskripsi' => 'Deskripsi.']);

    Livewire::test(ListSubcpmks::class)
        ->assertActionEnabled('resetData');
});

it('tombol reset nonaktif saat subcpmk sudah dipetakan ke asesmen', function () {
    $subcpmk = Subcpmk::query()->create(['mk_cpmk_id' => $this->mkCpmk->id, 'semester_id' => $this->semester->id, 'kode' => 'SUB01', 'deskripsi' => 'Deskripsi.']);
    $evaluasi = Evaluasi::query()->firstOrFail();
    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id, 'semester_id' => $this->semester->id, 'evaluasi_id' => $evaluasi->id,
        'kode' => 'UTS', 'nama' => 'UTS', 'bobot' => 100,
    ]);
    SubcpmkKomponenPenilaian::query()->create(['subcpmk_id' => $subcpmk->id, 'komponen_penilaian_id' => $komponen->id, 'semester_id' => $this->semester->id, 'bobot' => 100]);

    Livewire::test(ListSubcpmks::class)
        ->assertActionDisabled('resetData');
});

it('reset menghapus subcpmk semester ini saja, tidak menyentuh semester lain', function () {
    $subcpmk = Subcpmk::query()->create(['mk_cpmk_id' => $this->mkCpmk->id, 'semester_id' => $this->semester->id, 'kode' => 'SUB01', 'deskripsi' => 'Deskripsi.']);

    $semesterLain = Semester::query()->create([
        'kode' => 'SMLN1', 'nama' => 'Semester Lain', 'jenis' => 'genap',
        'tahun_mulai' => 2025, 'tahun_selesai' => 2026, 'status_aktif' => false,
    ]);
    $subcpmkLain = Subcpmk::query()->create(['mk_cpmk_id' => $this->mkCpmk->id, 'semester_id' => $semesterLain->id, 'kode' => 'SUB01', 'deskripsi' => 'Deskripsi.']);

    Livewire::test(ListSubcpmks::class)
        ->callAction('resetData');

    $this->assertDatabaseMissing('subcpmk', ['id' => $subcpmk->id]);
    $this->assertDatabaseHas('subcpmk', ['id' => $subcpmkLain->id]);
});
