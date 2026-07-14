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
use App\Modules\MK\Filament\Pages\CplCpmkMatrix;
use App\Modules\MK\Filament\Pages\SubcpmkAsesmenMatrix;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\EditCpmk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
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
        'nama' => 'Kurikulum Interaksi Korma',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    KurikulumTerpilih::set($this->kurikulum->id);
    $this->actingAs($this->korma);
});

function seedMkKoordinatorContext(object $context): Mk
{
    $mk = Mk::factory()->create([
        'academic_unit_id' => $context->prodi->id,
        'koordinator_mk_id' => $context->korma->id,
    ]);
    MkUnit::factory()->forMk($mk)->forAcademicUnit($context->prodi)->create(['kode' => 'INT101']);
    MkTerpilih::set($mk->id);

    return $mk;
}

it('edit cpmk hanya menampilkan form tanpa relation manager', function () {
    $mk = seedMkKoordinatorContext($this);
    $cpmk = Cpmk::query()->create([
        'mk_id' => $mk->id,
        'kode' => 'CPMK-UI',
        'deskripsi' => 'Uji form sederhana.',
    ]);

    Livewire::test(EditCpmk::class, ['record' => $cpmk->getKey()])
        ->assertSuccessful()
        ->assertFormSet([
            'kode' => 'CPMK-UI',
            'deskripsi' => 'Uji form sederhana.',
        ])
        ->assertDontSee('Pemetaan CPL–CPMK', escape: false);
});

it('matriks cpl cpmk mencentang pemetaan mk cpmk', function () {
    $mk = seedMkKoordinatorContext($this);
    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $cplMk = CplMk::query()->create([
        'cpl_bok_id' => $cplBok->id,
        'mk_id' => $mk->id,
        'bobot' => 100,
    ]);

    $cpmk = Cpmk::query()->create([
        'mk_id' => $mk->id,
        'kode' => 'CPMK-INT',
        'deskripsi' => 'Interaksi uji.',
    ]);

    expect(MkCpmk::query()->where('cpmk_id', $cpmk->id)->exists())->toBeFalse();

    Livewire::test(CplCpmkMatrix::class)
        ->call('toggle', $cpmk->id, $cpl->id);

    expect(MkCpmk::query()->where('cpmk_id', $cpmk->id)->where('cpl_mk_id', $cplMk->id)->exists())->toBeTrue();
});

it('matriks cpl cpmk hanya menampilkan kolom cpl yang terpetakan pada mk terpilih', function () {
    $mk = seedMkKoordinatorContext($this);

    $cplTerpetakan = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-MAP']);
    $cplLain = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-LAIN']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cplTerpetakan->id, 'bok_id' => $bok->id]);
    CplMk::query()->create([
        'cpl_bok_id' => $cplBok->id,
        'mk_id' => $mk->id,
        'bobot' => 100,
    ]);

    Cpmk::query()->create([
        'mk_id' => $mk->id,
        'kode' => 'CPMK-KOL',
        'deskripsi' => 'Kolom uji.',
    ]);

    Livewire::test(CplCpmkMatrix::class)
        ->assertSee('CPL-MAP', escape: false)
        ->assertDontSee('CPL-LAIN', escape: false);
});

it('matriks subcpmk asesmen menyimpan bobot pivot', function () {
    $mk = seedMkKoordinatorContext($this);
    $mkUnit = MkUnit::query()->where('mk_id', $mk->id)->firstOrFail();
    $cpmk = Cpmk::query()->create([
        'mk_id' => $mk->id,
        'kode' => 'CPMK-BOB',
        'deskripsi' => 'Bobot uji.',
    ]);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $cplMk = CplMk::query()->create([
        'cpl_bok_id' => $cplBok->id,
        'mk_id' => $mk->id,
        'bobot' => 100,
    ]);

    $mkCpmk = MkCpmk::query()->create([
        'cpl_mk_id' => $cplMk->id,
        'cpmk_id' => $cpmk->id,
        'bobot' => 100,
    ]);

    $subcpmk = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id,
        'semester_id' => $this->semester->id,
        'kode' => 'SUB-INT',
        'deskripsi' => 'Sub uji.',
        'bobot' => 100,
    ]);

    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'koordinator_mk_id' => $this->korma->id,
    ]);

    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $evaluasi->id,
        'nama' => 'UTS Interaksi',
        'bobot' => 100,
    ]);

    Livewire::test(SubcpmkAsesmenMatrix::class)
        ->call('updateBobot', $komponen->id, $subcpmk->id, '40');

    $pivot = SubcpmkKomponenPenilaian::query()
        ->where('komponen_penilaian_id', $komponen->id)
        ->where('subcpmk_id', $subcpmk->id)
        ->first();

    expect($pivot?->bobot)->toBe(40.0)
        ->and($pivot?->semester_id)->toBe($this->semester->id);

    // Bobot Sub-CPMK dihitung ulang otomatis: komponen 100% x pivot 40% = 40.
    expect((float) $subcpmk->fresh()->bobot)->toBe(40.0);

    Livewire::test(SubcpmkAsesmenMatrix::class)
        ->call('updateBobot', $komponen->id, $subcpmk->id, null);

    expect(SubcpmkKomponenPenilaian::query()
        ->where('komponen_penilaian_id', $komponen->id)
        ->where('subcpmk_id', $subcpmk->id)
        ->exists())->toBeFalse()
        ->and((float) $subcpmk->fresh()->bobot)->toBe(0.0);
});

it('matriks subcpmk asesmen membatasi bobot pivot maksimal sisa kapasitas Asesmen', function () {
    $mk = seedMkKoordinatorContext($this);
    $mkUnit = MkUnit::query()->where('mk_id', $mk->id)->firstOrFail();
    $cpmk = Cpmk::query()->create(['mk_id' => $mk->id, 'kode' => 'CPMK-CAP', 'deskripsi' => 'Batas kapasitas.']);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);

    $subcpmk1 = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id, 'semester_id' => $this->semester->id, 'kode' => 'SUB-CAP-1', 'deskripsi' => 'Sub 1',
    ]);
    $subcpmk2 = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id, 'semester_id' => $this->semester->id, 'kode' => 'SUB-CAP-2', 'deskripsi' => 'Sub 2',
    ]);

    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id, 'semester_id' => $this->semester->id, 'kode_kelas' => 'A', 'koordinator_mk_id' => $this->korma->id,
    ]);

    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id, 'semester_id' => $this->semester->id, 'evaluasi_id' => $evaluasi->id, 'nama' => 'UTS Kapasitas', 'bobot' => 10,
    ]);

    Livewire::test(SubcpmkAsesmenMatrix::class)
        ->call('updateBobot', $komponen->id, $subcpmk1->id, '6');

    // Sisa kapasitas komponen ini tinggal 4 (10 - 6) — input 8 harus
    // dipotong ke sisa yang benar-benar tersedia, bukan diloloskan mentah.
    Livewire::test(SubcpmkAsesmenMatrix::class)
        ->call('updateBobot', $komponen->id, $subcpmk2->id, '8');

    $pivot2 = SubcpmkKomponenPenilaian::query()
        ->where('komponen_penilaian_id', $komponen->id)
        ->where('subcpmk_id', $subcpmk2->id)
        ->first();

    expect((float) $pivot2?->bobot)->toBe(4.0);
});

it('cpmk belum diinteraksikan bila belum ada mk cpmk', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $cpmk = Cpmk::query()->create([
        'mk_id' => $mk->id,
        'kode' => 'CPMK-BARU',
        'deskripsi' => 'Belum dipetakan.',
    ]);

    expect($cpmk->belumDiinteraksikan())->toBeTrue();
});
