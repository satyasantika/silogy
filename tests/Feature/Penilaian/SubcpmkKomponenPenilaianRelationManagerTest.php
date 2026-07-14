<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\EditKomponenPenilaian;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\RelationManagers\SubcpmkKomponenPenilaianRelationManager;
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
    $this->korma = User::where('username', 'korma')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'koordinator_mk_id' => $this->korma->id]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create(['kode' => 'IF101']);
    KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'koordinator_mk_id' => $this->korma->id,
    ]);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $this->mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $this->mk->id, 'kode' => 'CPMK-01', 'deskripsi' => 'Uji']);
    $this->mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Relation Manager',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    $this->komponen = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => Evaluasi::query()->where('kode', 'uts')->value('id'),
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 10,
    ]);

    $this->sub1 = Subcpmk::query()->create([
        'mk_cpmk_id' => $this->mkCpmk->id, 'semester_id' => $this->semester->id, 'kode' => 'SUB-01', 'deskripsi' => 'Sub 1',
    ]);
    $this->sub2 = Subcpmk::query()->create([
        'mk_cpmk_id' => $this->mkCpmk->id, 'semester_id' => $this->semester->id, 'kode' => 'SUB-02', 'deskripsi' => 'Sub 2',
    ]);
});

it('menolak bobot yang melebihi sisa kapasitas Asesmen saat menambah Sub-CPMK', function () {
    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $this->sub1->id,
        'komponen_penilaian_id' => $this->komponen->id,
        'bobot' => 6,
    ]);

    Livewire::test(SubcpmkKomponenPenilaianRelationManager::class, [
        'ownerRecord' => $this->komponen,
        'pageClass' => EditKomponenPenilaian::class,
    ])
        ->callTableAction('create', data: ['subcpmk_id' => $this->sub2->id, 'bobot' => 5])
        ->assertHasTableActionErrors(['bobot']);

    expect(SubcpmkKomponenPenilaian::query()->where('subcpmk_id', $this->sub2->id)->exists())->toBeFalse();
});

it('menerima bobot dalam batas sisa kapasitas Asesmen saat menambah Sub-CPMK', function () {
    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $this->sub1->id,
        'komponen_penilaian_id' => $this->komponen->id,
        'bobot' => 6,
    ]);

    Livewire::test(SubcpmkKomponenPenilaianRelationManager::class, [
        'ownerRecord' => $this->komponen,
        'pageClass' => EditKomponenPenilaian::class,
    ])
        ->callTableAction('create', data: ['subcpmk_id' => $this->sub2->id, 'bobot' => 4])
        ->assertHasNoTableActionErrors();

    expect((float) SubcpmkKomponenPenilaian::query()->where('subcpmk_id', $this->sub2->id)->value('bobot'))->toBe(4.0);
});

it('mengizinkan mengedit pivot yang sama hingga bobot Asesmen penuh (mengecualikan bobotnya sendiri)', function () {
    $pivot = SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $this->sub1->id,
        'komponen_penilaian_id' => $this->komponen->id,
        'bobot' => 6,
    ]);

    Livewire::test(SubcpmkKomponenPenilaianRelationManager::class, [
        'ownerRecord' => $this->komponen,
        'pageClass' => EditKomponenPenilaian::class,
    ])
        ->callTableAction('edit', $pivot, data: ['subcpmk_id' => $this->sub1->id, 'bobot' => 10])
        ->assertHasNoTableActionErrors();

    expect((float) $pivot->fresh()->bobot)->toBe(10.0);
});

it('memperbarui visibilitas tombol Normalisasi begitu bobot Asesmen diubah di halaman edit, tanpa reload', function () {
    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $this->sub1->id,
        'komponen_penilaian_id' => $this->komponen->id,
        'bobot' => 10,
    ]);

    $card = Livewire::test(SubcpmkKomponenPenilaianRelationManager::class, [
        'ownerRecord' => $this->komponen,
        'pageClass' => EditKomponenPenilaian::class,
    ]);

    $card->assertTableActionHidden('normalisasiBobotSubcpmk');

    Livewire::test(EditKomponenPenilaian::class, ['record' => $this->komponen->getKey()])
        ->fillForm(['bobot' => 20])
        ->call('save')
        ->assertDispatched('komponen-penilaian-bobot-diperbarui');

    $card->dispatch('komponen-penilaian-bobot-diperbarui')
        ->assertTableActionVisible('normalisasiBobotSubcpmk');
});
