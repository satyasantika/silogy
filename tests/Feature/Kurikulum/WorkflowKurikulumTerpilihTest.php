<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\ListBoks;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Pages\CplBokMatrix;
use App\Modules\Kurikulum\Filament\Pages\CplMkMatrix;
use App\Modules\Kurikulum\Filament\Pages\ProfilCplMatrix;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\CreateKurikulum;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\ListKurikulums;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
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
    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();

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

it('menu unit akademik hanya tampil untuk super admin', function () {
    foreach (['timkur', 'adminprodi', 'dosentimkur'] as $username) {
        $this->actingAs(User::query()->where('username', $username)->firstOrFail());
        expect(AcademicUnitResource::shouldRegisterNavigation())->toBeFalse();
    }

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

it('admin prodi tidak melihat menu profil lulusan', function () {
    $this->actingAs(User::query()->where('username', 'adminprodi')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    expect(ProfilLulusanResource::shouldRegisterNavigation())->toBeFalse()
        ->and(ProfilLulusanResource::canAccess())->toBeFalse();
});

it('daftar kurikulum menampilkan record dan ketersediaan menu', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());

    Livewire::test(ListKurikulums::class)
        ->loadTable()
        ->assertSee('Kurikulum Prodi 2026')
        ->assertSee('Program Studi')
        ->assertSee('Profil ·')
        ->assertSee('CPL ·')
        ->assertSee('Aktif');
});

it('card kurikulum terpilih menampilkan Sedang dikerjakan dan aksi kerjakan mengganti session', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Prodi Alternatif',
        'tahun' => 2027,
        'is_active' => false,
    ]);

    KurikulumTerpilih::set($this->kurikulumProdi->id);

    Livewire::test(ListKurikulums::class)
        ->loadTable()
        ->assertSee('Sedang dikerjakan')
        ->assertSee('Nonaktif')
        ->callTableAction('kerjakan', $kurikulumLain);

    expect(KurikulumTerpilih::currentId())->toBe($kurikulumLain->id);
});

it('scope kurikulum terpilih diterapkan otomatis pada daftar cpl', function () {
    $this->actingAs(User::query()->where('username', 'dosentimkur')->firstOrFail());

    $cplProdi = Cpl::factory()->forKurikulum($this->kurikulumProdi)->create(['kode' => 'CPL-AUTO-PRODI']);
    $cplFak = Cpl::factory()->forKurikulum($this->kurikulumFak)->create(['kode' => 'CPL-AUTO-FAK']);

    KurikulumTerpilih::set($this->kurikulumProdi->id);

    Livewire::test(ListCpls::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$cplProdi])
        ->assertCanNotSeeTableRecords([$cplFak]);

    KurikulumTerpilih::set($this->kurikulumFak->id);

    Livewire::test(ListCpls::class)
        ->loadTable()
        ->assertCanNotSeeTableRecords([$cplProdi])
        ->assertCanSeeTableRecords([$cplFak]);

    expect(KurikulumTerpilih::currentId())->toBe($this->kurikulumFak->id);
});

it('kurikulum baru di prodi yang sama tidak menampilkan cpl kurikulum lama', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());

    $cplLama = Cpl::factory()->forKurikulum($this->kurikulumProdi)->create(['kode' => 'CPL-LAMA-01']);

    $kurikulumBaru = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Prodi Baru Kosong',
        'tahun' => 2027,
        'is_active' => true,
    ]);

    KurikulumTerpilih::set($kurikulumBaru->id);

    Livewire::test(ListCpls::class)
        ->loadTable()
        ->assertCanNotSeeTableRecords([$cplLama]);

    expect(KurikulumResource::ketersediaanMenu($kurikulumBaru)['cpl'])->toBeFalse();
});

it('membuat kurikulum baru langsung menjadikannya kurikulum yang dikerjakan, tanpa perlu klik kerjakan manual', function () {
    $dosenTimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosenTimkur);

    // User sedang mengerjakan kurikulum prodi (lama) sebelum membuat kurikulum baru.
    KurikulumTerpilih::set($this->kurikulumProdi->id);
    expect(KurikulumTerpilih::currentId())->toBe($this->kurikulumProdi->id);

    Livewire::test(CreateKurikulum::class)
        ->fillForm([
            'academic_unit_id' => $this->univ->id,
            'nama' => 'Kurikulum Universitas Baru',
            'tahun' => 2027,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $kurikulumBaru = Kurikulum::query()->where('nama', 'Kurikulum Universitas Baru')->firstOrFail();

    expect(KurikulumTerpilih::currentId())->toBe($kurikulumBaru->id);

    // CPL milik kurikulum lama tidak ikut tampil pada kurikulum baru yang kosong.
    $cplLama = Cpl::factory()->forKurikulum($this->kurikulumProdi)->create(['kode' => 'CPL-SEBELUM-BARU']);

    Livewire::test(ListCpls::class)
        ->loadTable()
        ->assertCanNotSeeTableRecords([$cplLama])
        ->assertCountTableRecords(0);
});

it('default() memilih kurikulum is_active terbaru bila lebih dari satu aktif pada unit yang sama', function () {
    $dosenTimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosenTimkur);

    // Paksa unit terpilih ke Universitas supaya default() menempuh cabang
    // where('academic_unit_id', $unitId) yang baru saja diberi orderBy —
    // tanpa ini, kurikulum prodi/fakultas dari beforeEach() akan menang
    // lewat tie-break kedalaman unit sebelum sempat membandingkan dua
    // kandidat universitas di bawah.
    \App\Modules\Institusi\Support\AcademicUnitTerpilih::set($this->univ->id);

    $kurikulumLamaAktif = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Univ Aktif Lama',
        'tahun' => 2020,
        'is_active' => true,
        'created_at' => now()->subDays(10),
    ]);

    $kurikulumBaruAktif = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Univ Aktif Baru',
        'tahun' => 2027,
        'is_active' => true,
        'created_at' => now(),
    ]);

    expect(KurikulumTerpilih::default($dosenTimkur)?->id)->toBe($kurikulumBaruAktif->id)
        ->and($kurikulumLamaAktif->id)->not->toBe($kurikulumBaruAktif->id);
});

it('banner kurikulum terpilih menampilkan hierarki unit', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    $this->prodi->loadMissing('parent.parent');
    $hierarchy = KurikulumTerpilih::unitHierarchyLabel($this->prodi);

    $html = KurikulumTerpilih::bannerHtml()->toHtml();

    expect($html)
        ->toContain('Kurikulum Prodi 2026')
        ->toContain($hierarchy);
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

it('matriks interaksi mengikuti role aktif pada dosentimkur', function () {
    $dosenTimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosenTimkur);

    // Pastikan ada kurikulum terpilih di unit tempat ia berstatus tim kurikulum.
    KurikulumTerpilih::set(
        Kurikulum::query()
            ->where('academic_unit_id', AcademicUnit::query()->where('type', 'study_program')->value('id'))
            ->value('id')
            ?? Kurikulum::query()->create([
                'academic_unit_id' => AcademicUnit::query()->where('type', 'study_program')->value('id'),
                'nama' => 'Kurikulum Interaksi Role',
                'tahun' => 2026,
                'is_active' => true,
            ])->id,
    );

    ActiveRole::set('Dosen Pengampu');

    expect(CplBokMatrix::canAccess())->toBeFalse()
        ->and(CplMkMatrix::canAccess())->toBeFalse()
        ->and(CplBokMatrix::shouldRegisterNavigation())->toBeFalse()
        ->and(CplMkMatrix::shouldRegisterNavigation())->toBeFalse();

    ActiveRole::set('Tim Kurikulum');

    expect(CplBokMatrix::canAccess())->toBeTrue()
        ->and(CplMkMatrix::canAccess())->toBeTrue()
        ->and(CplBokMatrix::shouldRegisterNavigation())->toBeTrue()
        ->and(CplMkMatrix::shouldRegisterNavigation())->toBeTrue();
});

/**
 * Konsekuensi adaptasi MK: prodi mengadaptasi satu MK milik universitas yang
 * sudah dipetakan universitas ke satu CPL/BoK-nya sendiri (bobot 60) —
 * rantai mk_units -> cpl_mk -> cpl_bok inilah yang membuat CPL/BoK
 * universitas tsb tersingkap otomatis di semesta prodi.
 *
 * @return array{mk: Mk, cpl: Cpl, bok: Bok, cplBok: CplBok}
 */
function siapkanAdaptasiUnivWorkflow(object $context): array
{
    $mkUniv = Mk::factory()->forAcademicUnit($context->univ)->create(['nama' => 'MK Adaptasi Univ']);
    $cplUniv = Cpl::factory()->forAcademicUnit($context->univ)->create(['kode' => 'CPL-UNIV-1']);
    $bokUniv = Bok::factory()->forAcademicUnit($context->univ)->create(['kode' => 'BOK-UNIV-1']);
    $cplBokUniv = CplBok::query()->create(['cpl_id' => $cplUniv->id, 'bok_id' => $bokUniv->id]);
    CplMk::query()->create(['cpl_bok_id' => $cplBokUniv->id, 'mk_id' => $mkUniv->id, 'bobot' => 60]);

    MkUnit::factory()->forAcademicUnit($context->prodi)->forMk($mkUniv)->create(['is_active' => true]);

    return ['mk' => $mkUniv, 'cpl' => $cplUniv, 'bok' => $bokUniv, 'cplBok' => $cplBokUniv];
}

it('listcpls dan listboks menampilkan cpl/bok universitas yang teradaptasi lewat mk', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    $adaptasi = siapkanAdaptasiUnivWorkflow($this);

    Livewire::test(ListCpls::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$adaptasi['cpl']]);

    Livewire::test(ListBoks::class)
        ->loadTable()
        ->assertCanSeeTableRecords([$adaptasi['bok']]);
});

it('cplbokmatrix menampilkan pasangan adaptasi, menolak lepas pasangan murni asing, mengizinkan pasangan baru menyilang unit', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    $adaptasi = siapkanAdaptasiUnivWorkflow($this);
    $cplProdi = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-PRODI-X']);

    $page = Livewire::test(CplBokMatrix::class)
        ->assertSee($adaptasi['cpl']->kode)
        ->assertSee($adaptasi['bok']->kode);

    // Pasangan murni asing (cpl univ x bok univ) tidak boleh diubah dari prodi.
    $page->call('toggle', $adaptasi['cpl']->id, $adaptasi['bok']->id);
    expect(CplBok::query()->where('cpl_id', $adaptasi['cpl']->id)->where('bok_id', $adaptasi['bok']->id)->exists())->toBeTrue();

    // Pasangan baru menyilang unit (CPL prodi x BoK universitas) boleh dibuat.
    $page->call('toggle', $cplProdi->id, $adaptasi['bok']->id);
    expect(CplBok::query()->where('cpl_id', $cplProdi->id)->where('bok_id', $adaptasi['bok']->id)->exists())->toBeTrue();
});

it('cplmkmatrix menjumlahkan bobot mk prodi dan mk adaptasi ke kolom cplbok yang sama (bukti perbaikan bug axis)', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    $adaptasi = siapkanAdaptasiUnivWorkflow($this);
    $mkProdi = Mk::factory()->forAcademicUnit($this->prodi)->create(['nama' => 'MK Prodi Sendiri']);

    Livewire::test(CplMkMatrix::class)
        ->call('updateBobot', $mkProdi->id, $adaptasi['cplBok']->id, '30');

    expect((float) CplMk::query()
        ->where('mk_id', $mkProdi->id)
        ->where('cpl_bok_id', $adaptasi['cplBok']->id)
        ->value('bobot'))->toBe(30.0);

    // Total kolom = 60 (mk adaptasi) + 30 (mk prodi) = 90, bukan dua total terpisah per baris.
    Livewire::test(CplMkMatrix::class)->assertSee('Σ 90%');
});

it('profilcplmatrix bisa toggle profil x cpl universitas yang teradaptasi', function () {
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    KurikulumTerpilih::set($this->kurikulumProdi->id);

    $adaptasi = siapkanAdaptasiUnivWorkflow($this);
    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $this->kurikulumProdi->id,
        'kode' => 'PL-ADAPT',
        'deskripsi' => 'Profil uji adaptasi',
    ]);

    Livewire::test(ProfilCplMatrix::class)
        ->assertSee($adaptasi['cpl']->kode)
        ->call('toggle', $adaptasi['cpl']->id, $profil->id);

    expect(CplProfilLulusan::query()
        ->where('cpl_id', $adaptasi['cpl']->id)
        ->where('profil_lulusan_id', $profil->id)
        ->exists())->toBeTrue();
});
