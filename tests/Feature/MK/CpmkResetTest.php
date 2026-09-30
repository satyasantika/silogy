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
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\ListCpmks;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Support\MkTerpilih;
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

    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Reset CPMK',
        'kode' => 'RSTCP',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create(['koordinator_mk_id' => $this->korma->id]);

    $this->actingAs($this->korma);
    KurikulumTerpilih::set($this->kurikulum->id);
    MkTerpilih::set($this->mk->id);
    SemesterTerpilih::set($this->mk->id, $this->semester->id);
});

function buatCplMkUntukCpmkTest(Kurikulum $kurikulum, Mk $mk): CplMk
{
    $cpl = Cpl::factory()->forKurikulum($kurikulum)->create();
    $bok = Bok::factory()->forKurikulum($kurikulum)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);

    return CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
}

it('tombol buat tidak lagi ada, impor massal tampil saat CPMK semester ini masih kosong', function () {
    Livewire::test(ListCpmks::class)
        ->assertActionDoesNotExist('create')
        ->assertActionExists('bulkImport');
});

it('impor massal terkunci dan tombol ajukan perubahan muncul saat CPMK semester ini sudah ada', function () {
    cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);

    // Mengubah CPMK yang sudah berjalan butuh persetujuan Tim Kurikulum;
    // jalan keluarnya adalah mengajukan usulan, bukan mengimpor diam-diam.
    Livewire::test(ListCpmks::class)
        ->assertActionHidden('bulkImport')
        ->assertActionVisible('ajukanPerubahanCpmk');
});

it('tombol reset aktif saat belum ada cpmk yang dipetakan ke cpl-mk', function () {
    cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);

    Livewire::test(ListCpmks::class)
        ->assertActionEnabled('resetData');
});

it('tombol reset nonaktif saat cpmk sudah dipetakan ke cpl-mk', function () {
    $cpmk = cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);
    $cplMk = buatCplMkUntukCpmkTest($this->kurikulum, $this->mk);
    MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);

    Livewire::test(ListCpmks::class)
        ->assertActionDisabled('resetData');
});

it('reset melepas cpmk mk ini dari semester terpilih tanpa menyentuh mk lain', function () {
    $cpmk = cpmkUntukSemester($this->mk, $this->semester, ['kode' => 'CPMK01']);

    $mkLain = Mk::factory()->forKurikulum($this->kurikulum)->create(['koordinator_mk_id' => $this->korma->id]);
    $cpmkLain = cpmkUntukSemester($mkLain, $this->semester, ['kode' => 'CPMK01']);

    Livewire::test(ListCpmks::class)
        ->callAction('resetData');

    $this->assertDatabaseMissing('cpmk', ['id' => $cpmk->id]);
    $this->assertDatabaseHas('cpmk', ['id' => $cpmkLain->id]);
});
