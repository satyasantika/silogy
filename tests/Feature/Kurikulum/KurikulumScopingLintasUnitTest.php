<?php

use App\Models\User;
use App\Modules\BoK\Filament\Resources\BokResource\Pages\ListBoks;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\EditCpl;
use App\Modules\CPL\Filament\Resources\CplResource\Pages\ListCpls;
use App\Modules\CPL\Filament\Resources\CplResource\RelationManagers\BokRelationManager;
use App\Modules\CPL\Filament\Resources\CplResource\RelationManagers\ProfilLulusanRelationManager as CplProfilLulusanRelationManager;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Pages\CplBokMatrix;
use App\Modules\Kurikulum\Filament\Pages\CplMkMatrix;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\EditKurikulum;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\RelationManagers\CplMkRelationManager;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkResource\Pages\ListMks;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Services\MkUnitUpdateMassalService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Filament AttachAction menyimpan closure recordSelectOptionsQuery() sebagai
 * properti protected — diakses lewat reflection supaya test bisa memverifikasi
 * query pembatas opsi secara langsung, tanpa bergantung pada perilaku validasi
 * Select saat submit (yang untuk nilai di luar opsi bisa berujung pada
 * ErrorException internal Filament, bukan kegagalan yang bersih/mudah diuji).
 */
function opsiAttachRecordIds(object $component, string $actionName = 'attach'): \Illuminate\Support\Collection
{
    $action = $component->instance()->getTable()->getAction($actionName);

    $ref = new ReflectionProperty($action, 'modifyRecordSelectOptionsQueryUsing');
    $ref->setAccessible(true);
    $closure = $ref->getValue($action);

    $relationship = \Illuminate\Database\Eloquent\Relations\Relation::noConstraints(
        fn () => $component->instance()->getTable()->getRelationship(),
    );
    $related = $relationship->getRelated();

    $query = $closure($related->newQuery());

    return $query->pluck($related->getKeyName());
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->timkurfak = User::query()->where('username', 'timkurfak')->firstOrFail();
    $this->dosentimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();
});

it('listcpls/listboks tidak bocor ke kurikulum lain meski unit punya adaptasi aktif (bug precedence OR)', function () {
    // Pemicu bug: adaptedCplIdsAcrossUnit()/adaptedBokIdsAcrossUnit() TIDAK kosong,
    // sehingga getEloquentQuery() dulu menambah orWhereIn('id', $adaptasi) top-level.
    // Klausa academic_unit_id IN (...) lalu menjadi OR independen yang diloloskan
    // meskipun kurikulum_id tidak cocok — lihat perbaikan CplResource/BokResource.
    $this->actingAs($this->dosentimkur);

    $kurikulumFak = Kurikulum::query()->create([
        'academic_unit_id' => $this->fakultas->id,
        'nama' => 'Fak Sumber Adaptasi Precedence',
        'tahun' => 2024,
        'is_active' => true,
    ]);
    $kurikulumA = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Univ Dengan Adaptasi',
        'tahun' => 2025,
        'is_active' => true,
    ]);
    $kurikulumB = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Kurikulum OBE 2025',
        'tahun' => 2026,
        'is_active' => false,
    ]);

    $mkAsal = Mk::factory()->forKurikulum($kurikulumFak)->create(['nama' => 'MK Sumber Adaptasi Precedence']);
    $cplAsal = Cpl::factory()->forKurikulum($kurikulumFak)->create(['kode' => 'CPL-ASAL-PREC']);
    $bokAsal = Bok::factory()->forKurikulum($kurikulumFak)->create(['kode' => 'BOK-ASAL-PREC']);
    $cplBokAsal = CplBok::query()->create(['cpl_id' => $cplAsal->id, 'bok_id' => $bokAsal->id]);
    CplMk::query()->create(['cpl_bok_id' => $cplBokAsal->id, 'mk_id' => $mkAsal->id, 'bobot' => 100]);
    MkUnit::factory()->forMk($mkAsal)->forKurikulum($kurikulumA)->create(['is_active' => true]);

    $cplA = Cpl::factory()->forKurikulum($kurikulumA)->create(['kode' => 'CPL-UNIV-A']);
    $bokA = Bok::factory()->forKurikulum($kurikulumA)->create(['kode' => 'BOK-UNIV-A']);
    $cplB = Cpl::factory()->forKurikulum($kurikulumB)->create(['kode' => 'CPL-UNIV-B']);
    $bokB = Bok::factory()->forKurikulum($kurikulumB)->create(['kode' => 'BOK-UNIV-B']);

    expect(\App\Modules\Kurikulum\Support\CplBokAdaptasiScope::adaptedCplIdsAcrossUnit($this->univ->id))
        ->not->toBeEmpty()
        ->and(\App\Modules\Kurikulum\Support\CplBokAdaptasiScope::adaptedBokIdsAcrossUnit($this->univ->id))
        ->not->toBeEmpty();

    KurikulumTerpilih::set($kurikulumB->id);

    Livewire::test(ListCpls::class)->loadTable()
        ->assertCanSeeTableRecords([$cplB])
        ->assertCanNotSeeTableRecords([$cplA, $cplAsal]);

    Livewire::test(ListBoks::class)->loadTable()
        ->assertCanSeeTableRecords([$bokB])
        ->assertCanNotSeeTableRecords([$bokA, $bokAsal]);
});

it('listcpls/listboks/listmks di level fakultas hanya menampilkan baris kurikulum yang dikerjakan, bukan kurikulum lain unit yang sama', function () {
    $this->actingAs($this->dosentimkur);

    $kurikulumAktif = Kurikulum::query()->create(['academic_unit_id' => $this->fakultas->id, 'nama' => 'Fak Aktif', 'tahun' => 2026, 'is_active' => true]);
    $kurikulumLama = Kurikulum::query()->create(['academic_unit_id' => $this->fakultas->id, 'nama' => 'Fak Lama', 'tahun' => 2020, 'is_active' => false]);

    $cplAktif = Cpl::factory()->forKurikulum($kurikulumAktif)->create(['kode' => 'CPL-FAK-AKTIF']);
    $cplLama = Cpl::factory()->forKurikulum($kurikulumLama)->create(['kode' => 'CPL-FAK-LAMA']);
    $bokAktif = Bok::factory()->forKurikulum($kurikulumAktif)->create(['kode' => 'BOK-FAK-AKTIF']);
    $bokLama = Bok::factory()->forKurikulum($kurikulumLama)->create(['kode' => 'BOK-FAK-LAMA']);
    $mkAktif = Mk::factory()->forKurikulum($kurikulumAktif)->create(['nama' => 'MK Fak Aktif']);
    $mkLama = Mk::factory()->forKurikulum($kurikulumLama)->create(['nama' => 'MK Fak Lama']);

    KurikulumTerpilih::set($kurikulumAktif->id);

    Livewire::test(ListCpls::class)->loadTable()
        ->assertCanSeeTableRecords([$cplAktif])
        ->assertCanNotSeeTableRecords([$cplLama]);

    Livewire::test(ListBoks::class)->loadTable()
        ->assertCanSeeTableRecords([$bokAktif])
        ->assertCanNotSeeTableRecords([$bokLama]);

    Livewire::test(ListMks::class)->loadTable()
        ->assertCanSeeTableRecords([$mkAktif])
        ->assertCanNotSeeTableRecords([$mkLama]);
});

it('listcpls/listboks di level universitas hanya menampilkan baris kurikulum yang dikerjakan', function () {
    $this->actingAs($this->dosentimkur);

    $kurikulumAktif = Kurikulum::query()->create(['academic_unit_id' => $this->univ->id, 'nama' => 'Univ Aktif', 'tahun' => 2026, 'is_active' => true]);
    $kurikulumLama = Kurikulum::query()->create(['academic_unit_id' => $this->univ->id, 'nama' => 'Univ Lama', 'tahun' => 2020, 'is_active' => false]);

    $cplAktif = Cpl::factory()->forKurikulum($kurikulumAktif)->create(['kode' => 'CPL-UNIV-AKTIF']);
    $cplLama = Cpl::factory()->forKurikulum($kurikulumLama)->create(['kode' => 'CPL-UNIV-LAMA']);

    KurikulumTerpilih::set($kurikulumAktif->id);

    Livewire::test(ListCpls::class)->loadTable()
        ->assertCanSeeTableRecords([$cplAktif])
        ->assertCanNotSeeTableRecords([$cplLama]);
});

it('cplbokmatrix dan cplmkmatrix di level fakultas tidak menampilkan cpl/bok/mk dari kurikulum lain pada unit yang sama', function () {
    $this->actingAs($this->dosentimkur);

    $kurikulumAktif = Kurikulum::query()->create(['academic_unit_id' => $this->fakultas->id, 'nama' => 'Fak Matrix Aktif', 'tahun' => 2026, 'is_active' => true]);
    $kurikulumLama = Kurikulum::query()->create(['academic_unit_id' => $this->fakultas->id, 'nama' => 'Fak Matrix Lama', 'tahun' => 2020, 'is_active' => false]);

    $cplAktif = Cpl::factory()->forKurikulum($kurikulumAktif)->create(['kode' => 'CPL-MTX-AKTIF']);
    $bokAktif = Bok::factory()->forKurikulum($kurikulumAktif)->create(['kode' => 'BOK-MTX-AKTIF']);
    $mkAktif = Mk::factory()->forKurikulum($kurikulumAktif)->create(['nama' => 'MK Matrix Aktif']);
    // CplMkMatrix hanya merender MK bila ada minimal satu pasangan CPL-BoK
    // (kolom matriks) — buat pasangannya supaya baris MK ikut tampil.
    CplBok::query()->create(['cpl_id' => $cplAktif->id, 'bok_id' => $bokAktif->id]);

    $cplLama = Cpl::factory()->forKurikulum($kurikulumLama)->create(['kode' => 'CPL-MTX-LAMA']);
    $bokLama = Bok::factory()->forKurikulum($kurikulumLama)->create(['kode' => 'BOK-MTX-LAMA']);
    $mkLama = Mk::factory()->forKurikulum($kurikulumLama)->create(['nama' => 'MK Matrix Lama']);
    CplBok::query()->create(['cpl_id' => $cplLama->id, 'bok_id' => $bokLama->id]);

    KurikulumTerpilih::set($kurikulumAktif->id);

    Livewire::test(CplBokMatrix::class)
        ->assertSee('CPL-MTX-AKTIF')
        ->assertSee('BOK-MTX-AKTIF')
        ->assertDontSee('CPL-MTX-LAMA')
        ->assertDontSee('BOK-MTX-LAMA');

    Livewire::test(CplMkMatrix::class)
        ->assertSee('MK Matrix Aktif')
        ->assertDontSee('MK Matrix Lama');
});

it('picker attach BoK pada CPL hanya menawarkan BoK dari kurikulum yang sama, bukan kurikulum lain di unit yang sama', function () {
    $this->actingAs($this->timkur);

    $kurikulumAktif = Kurikulum::query()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Prodi Picker Aktif', 'tahun' => 2026, 'is_active' => true]);
    $kurikulumLama = Kurikulum::query()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Prodi Picker Lama', 'tahun' => 2020, 'is_active' => false]);

    $cpl = Cpl::factory()->forKurikulum($kurikulumAktif)->create();
    $bokSamaKurikulum = Bok::factory()->forKurikulum($kurikulumAktif)->create();
    $bokKurikulumLain = Bok::factory()->forKurikulum($kurikulumLama)->create();

    $component = Livewire::test(BokRelationManager::class, [
        'ownerRecord' => $cpl,
        'pageClass' => EditCpl::class,
    ]);

    $opsi = opsiAttachRecordIds($component);

    expect($opsi->contains($bokSamaKurikulum->id))->toBeTrue()
        ->and($opsi->contains($bokKurikulumLain->id))->toBeFalse();
});

it('picker attach Profil Lulusan pada CPL hanya menawarkan profil dari kurikulum yang sama', function () {
    $this->actingAs($this->timkur);

    $kurikulumAktif = Kurikulum::query()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Prodi Profil Aktif', 'tahun' => 2026, 'is_active' => true]);
    $kurikulumLama = Kurikulum::query()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Prodi Profil Lama', 'tahun' => 2020, 'is_active' => false]);

    $cpl = Cpl::factory()->forKurikulum($kurikulumAktif)->create();
    $profilSamaKurikulum = ProfilLulusan::query()->create(['kurikulum_id' => $kurikulumAktif->id, 'kode' => 'PL-1', 'deskripsi' => 'Profil kurikulum aktif']);
    $profilKurikulumLain = ProfilLulusan::query()->create(['kurikulum_id' => $kurikulumLama->id, 'kode' => 'PL-1', 'deskripsi' => 'Profil kurikulum lama']);

    $component = Livewire::test(CplProfilLulusanRelationManager::class, [
        'ownerRecord' => $cpl,
        'pageClass' => EditCpl::class,
    ]);

    $opsi = opsiAttachRecordIds($component);

    expect($opsi->contains($profilSamaKurikulum->id))->toBeTrue()
        ->and($opsi->contains($profilKurikulumLain->id))->toBeFalse();
});

it('picker pemetaan cpl-bok->mk pada halaman kurikulum hanya menawarkan cpl/bok/mk milik kurikulum yang diedit', function () {
    $this->actingAs($this->timkur);

    $kurikulumDiedit = Kurikulum::query()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Prodi Dipetakan', 'tahun' => 2026, 'is_active' => true]);
    $kurikulumLain = Kurikulum::query()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Prodi Lain Unit Sama', 'tahun' => 2020, 'is_active' => false]);

    $cplMilik = Cpl::factory()->forKurikulum($kurikulumDiedit)->create();
    $bokMilik = Bok::factory()->forKurikulum($kurikulumDiedit)->create();
    $cplBokMilik = CplBok::query()->create(['cpl_id' => $cplMilik->id, 'bok_id' => $bokMilik->id]);
    $mkMilik = Mk::factory()->forKurikulum($kurikulumDiedit)->create(['nama' => 'MK Milik Kurikulum Diedit']);

    $cplLain = Cpl::factory()->forKurikulum($kurikulumLain)->create();
    $bokLain = Bok::factory()->forKurikulum($kurikulumLain)->create();
    $cplBokLain = CplBok::query()->create(['cpl_id' => $cplLain->id, 'bok_id' => $bokLain->id]);
    $mkLain = Mk::factory()->forKurikulum($kurikulumLain)->create(['nama' => 'MK Milik Kurikulum Lain']);

    // Dibangun manual (bukan Livewire::test()) supaya hanya form() picker
    // yang dievaluasi — table() relation manager ini memakai relasi
    // Kurikulum::cplMks() yang whereColumn()-nya butuh tabel "kurikulum"
    // sudah ter-join di query luar (lihat komentar AnalisisMkProdiService::
    // pemetaanCplMk()), sehingga gagal bila di-render berdiri sendiri lewat
    // full mount Livewire::test() — di luar cakupan perbaikan ini.
    $rm = new CplMkRelationManager;
    $rm->ownerRecord = $kurikulumDiedit;

    $schema = $rm->form(\Filament\Schemas\Schema::make($rm));
    $mkOptions = collect($schema->getComponent('mk_id')?->getOptions() ?? []);
    $cplBokOptions = collect($schema->getComponent('cpl_bok_id')?->getOptions() ?? []);

    expect($mkOptions->has($mkMilik->id))->toBeTrue()
        ->and($mkOptions->has($mkLain->id))->toBeFalse()
        ->and($cplBokOptions->has($cplBokMilik->id))->toBeTrue()
        ->and($cplBokOptions->has($cplBokLain->id))->toBeFalse();
});

it('MkUnitUpdateMassalService tidak menimpa mk_units kurikulum lain yang mk-nya kebetulan bernama sama', function () {
    $this->actingAs($this->timkur);

    $kurikulumAktif = Kurikulum::query()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Prodi Update Massal Aktif', 'tahun' => 2026, 'is_active' => true]);
    $kurikulumLama = Kurikulum::query()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Prodi Update Massal Lama', 'tahun' => 2020, 'is_active' => false]);

    $mkAktif = Mk::factory()->forKurikulum($kurikulumAktif)->create(['nama' => 'Kalkulus Bersama']);
    $mkUnitAktif = MkUnit::factory()->forMk($mkAktif)->forKurikulum($kurikulumAktif)->create(['kode' => 'LAMA01', 'semester_ke' => 1]);

    $mkLama = Mk::factory()->forKurikulum($kurikulumLama)->create(['nama' => 'Kalkulus Bersama']);
    $mkUnitLama = MkUnit::factory()->forMk($mkLama)->forKurikulum($kurikulumLama)->create(['kode' => 'JANGAN-BERUBAH', 'semester_ke' => 5]);

    $service = app(MkUnitUpdateMassalService::class);
    $context = ['import_kurikulum_id' => $kurikulumAktif->id];

    $hasil = $service->resolveBaris(['nama' => 'Kalkulus Bersama', 'kode' => 'BARU01', 'semester_ke' => '2'], $context);

    expect($hasil['status'])->toBe('baru')
        ->and($hasil['existing_id'])->toBe($mkUnitAktif->id);

    $service->perbaruiPenawaran(MkUnit::query()->findOrFail($hasil['existing_id']), ['kode' => 'BARU01', 'semester_ke' => '2']);

    expect($mkUnitAktif->fresh()->kode)->toBe('BARU01')
        ->and($mkUnitLama->fresh()->kode)->toBe('JANGAN-BERUBAH')
        ->and($mkUnitLama->fresh()->semester_ke)->toBe(5);
});
