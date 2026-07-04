<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Pages\CplBokMatrix;
use App\Modules\Kurikulum\Filament\Pages\CplMkMatrix;
use App\Modules\Kurikulum\Filament\Pages\ProfilCplMatrix;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\ListKurikulums;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Mk;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();

    $this->kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Prodi 2026',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->kurikulumFak = Kurikulum::query()->create([
        'academic_unit_id' => $this->fakultas->id,
        'nama' => 'OBE-FKIP-2026',
        'tahun' => 2026,
        'is_active' => true,
    ]);
});

it('default kurikulum terpilih adalah kurikulum aktif pada unit terendah', function () {
    // dosentimkur adalah tim kurikulum di prodi, fakultas, dan universitas.
    $dosenTimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosenTimkur);

    expect(KurikulumTerpilih::current()?->id)->toBe($this->kurikulumProdi->id);
});

it('timkur fakultas mendapat default kurikulum fakultasnya dan tidak bisa memilih kurikulum prodi luar scope', function () {
    $this->actingAs(User::query()->where('username', 'timkurfak')->firstOrFail());

    expect(KurikulumTerpilih::current()?->id)->toBe($this->kurikulumFak->id);

    KurikulumTerpilih::set($this->kurikulumProdi->id);

    // Kurikulum prodi bukan scope timkurfak → pilihan diabaikan, kembali ke default.
    expect(KurikulumTerpilih::current()?->id)->toBe($this->kurikulumFak->id);
});

it('menu unit akademik tersembunyi untuk role tim kurikulum', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    expect(AcademicUnitResource::shouldRegisterNavigation())->toBeFalse();

    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());
    expect(AcademicUnitResource::shouldRegisterNavigation())->toBeTrue();
});

it('menu profil lulusan hanya tampil bila kurikulum terpilih milik prodi', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulumProdi->id);
    expect(ProfilLulusanResource::shouldRegisterNavigation())->toBeTrue();

    $this->actingAs(User::query()->where('username', 'timkurfak')->firstOrFail());
    session()->forget(KurikulumTerpilih::SESSION_KEY);
    expect(ProfilLulusanResource::shouldRegisterNavigation())->toBeFalse();
});

it('daftar kurikulum menampilkan record dan ketersediaan menu', function () {
    $this->actingAs(User::query()->where('username', 'superadmin')->firstOrFail());

    Livewire::test(ListKurikulums::class)
        ->loadTable()
        ->assertSee('Kurikulum Prodi 2026')
        ->assertSee('Profil ·')
        ->assertSee('CPL ·');
});

it('filter kurikulum diterapkan otomatis saat dipilih tanpa tombol terapkan', function () {
    $this->actingAs(User::query()->where('username', 'dosentimkur')->firstOrFail());

    $cplProdi = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-AUTO-PRODI']);
    $cplFak = Cpl::factory()->forAcademicUnit($this->fakultas)->create(['kode' => 'CPL-AUTO-FAK']);

    KurikulumTerpilih::set($this->kurikulumProdi->id);

    Livewire::test(ListCpls::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$cplProdi])
        ->assertCanNotSeeTableRecords([$cplFak])
        ->filterTable('kurikulum_terpilih', $this->kurikulumFak->id)
        ->assertCanNotSeeTableRecords([$cplProdi])
        ->assertCanSeeTableRecords([$cplFak]);

    expect(KurikulumTerpilih::currentId())->toBe($this->kurikulumFak->id);
});

it('ketersediaan menu kurikulum menampilkan status ada atau belum', function () {
    Cpl::factory()->count(2)->forAcademicUnit($this->prodi)->create();
    ProfilLulusan::query()->create([
        'kurikulum_id' => $this->kurikulumProdi->id,
        'kode' => 'PL-1',
        'deskripsi' => 'Profil uji',
    ]);

    $menu = KurikulumResource::ketersediaanMenu($this->kurikulumProdi->fresh());

    expect($menu['profil'])->toBeTrue()
        ->and($menu['cpl'])->toBeTrue()
        ->and($menu['bok'])->toBeFalse()
        ->and($menu['mk'])->toBeFalse();
});

it('matriks profil-cpl dapat memetakan dan melepas lewat toggle', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $this->kurikulumProdi->id,
        'kode' => 'PL-1',
        'deskripsi' => 'Profil uji',
    ]);
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();

    Livewire::test(ProfilCplMatrix::class)
        ->assertSee($profil->kode)
        ->assertSee($cpl->kode)
        ->call('toggle', $cpl->id, $profil->id);

    expect(CplProfilLulusan::query()->where('cpl_id', $cpl->id)->where('profil_lulusan_id', $profil->id)->exists())->toBeTrue();

    Livewire::test(ProfilCplMatrix::class)->call('toggle', $cpl->id, $profil->id);

    expect(CplProfilLulusan::query()->where('cpl_id', $cpl->id)->exists())->toBeFalse();
});

it('matriks cpl-bok dapat memetakan lewat toggle', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();

    Livewire::test(CplBokMatrix::class)->call('toggle', $cpl->id, $bok->id);

    expect(CplBok::query()->where('cpl_id', $cpl->id)->where('bok_id', $bok->id)->exists())->toBeTrue();
});

it('matriks cpl-bok menolak melepas centang bila sudah ada bobot cpl-mk', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);

    CplMk::query()->create([
        'mk_id' => $mk->id,
        'cpl_bok_id' => $cplBok->id,
        'bobot' => 40,
    ]);

    Livewire::test(CplBokMatrix::class)
        ->assertSee('hapus bobot pada interaksi CPL ↔ MK terlebih dahulu')
        ->call('toggle', $cpl->id, $bok->id);

    expect(CplBok::query()->where('cpl_id', $cpl->id)->where('bok_id', $bok->id)->exists())->toBeTrue();

    CplMk::query()->where('cpl_bok_id', $cplBok->id)->delete();

    Livewire::test(CplBokMatrix::class)->call('toggle', $cpl->id, $bok->id);

    expect(CplBok::query()->where('cpl_id', $cpl->id)->where('bok_id', $bok->id)->exists())->toBeFalse();
});

it('matriks cpl-mk menyimpan bobot dan menghitung total per mk', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-M1']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'BOK-M1']);
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'MK Matriks']);

    $page = Livewire::test(CplMkMatrix::class)
        ->call('updateBobot', $mk->id, $cplBok->id, '60');

    expect((float) CplMk::query()->where('mk_id', $mk->id)->where('cpl_bok_id', $cplBok->id)->value('bobot'))->toBe(60.0);

    // Total tampil setelah nama MK.
    $page = Livewire::test(CplMkMatrix::class);
    $page->assertSee('MK Matriks')->assertSee('Σ 60%');

    // Kosongkan → pivot terhapus.
    Livewire::test(CplMkMatrix::class)->call('updateBobot', $mk->id, $cplBok->id, '');

    expect(CplMk::query()->where('mk_id', $mk->id)->exists())->toBeFalse();
});

it('matriks tidak dapat diakses tanpa scope tim kurikulum', function () {
    $this->actingAs(User::query()->where('username', 'dosen')->firstOrFail());

    expect(CplBokMatrix::canAccess())->toBeFalse()
        ->and(ProfilCplMatrix::canAccess())->toBeFalse()
        ->and(CplMkMatrix::canAccess())->toBeFalse();
});
