<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\CreateKomponenPenilaian;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\EditKomponenPenilaian;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\ListKomponenPenilaians;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
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

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $this->korma = User::where('username', 'korma')->first();
    $this->superadmin = User::where('username', 'superadmin')->first();
    $this->semester = Semester::query()->where('status_aktif', true)->first();

    $this->mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create(['kode' => 'IF101']);

    $this->kelasA = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $this->kelasB = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'B',
        'koordinator_mk_id' => $this->korma->id,
    ]);

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Korma Massal',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
});

it('toolbar asesmen: filter semester tanpa indikator filter aktif dan tanpa urutkan menurut', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'ASES-01',
        'nama' => 'Kuis 1',
        'bobot' => 100,
    ]);

    Livewire::test(ListKomponenPenilaians::class)->loadTable()
        ->assertSee('Semester', escape: false)
        ->assertDontSee('Filter aktif', escape: false)
        ->assertDontSee('Urutkan menurut', escape: false)
        ->assertSeeHtml('data-silogy="banner-mk-header-panel"');
});

it('kode subcpmk pada card asesmen menjadi trigger keterangan tanpa membuka edit', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $this->mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create([
        'mk_id' => $this->mk->id,
        'kode' => 'CPMK-TRG',
        'deskripsi' => 'Deskripsi CPMK trigger.',
    ]);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);
    $sub = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id,
        'semester_id' => $this->semester->id,
        'kode' => 'SubCPMK-TRG',
        'deskripsi' => 'Deskripsi Sub-CPMK untuk trigger di card asesmen.',
    ]);

    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'ASES-TRG',
        'nama' => 'Asesmen Trigger',
        'bobot' => 100,
    ]);

    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $sub->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);

    $html = Livewire::test(ListKomponenPenilaians::class)->loadTable()->html();

    expect($html)
        ->toContain('data-silogy="kode-keterangan-trigger"')
        ->toContain('data-jenis="Sub-CPMK"')
        ->toContain('SubCPMK-TRG')
        ->toContain('Deskripsi Sub-CPMK untuk trigger di card asesmen.')
        ->toContain('onclick="event.stopPropagation(); event.preventDefault();"')
        ->toContain('@click.stop.prevent');
});

it('Tabel Rencana Evaluasi memakai trigger keterangan untuk kode CPL dan CPMK', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create([
        'kode' => 'CPL-RE',
        'deskripsi' => 'Deskripsi CPL di rencana evaluasi.',
    ]);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $this->mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create([
        'mk_id' => $this->mk->id,
        'kode' => 'CPMK-RE',
        'deskripsi' => 'Deskripsi CPMK di rencana evaluasi.',
    ]);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);
    $sub = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id,
        'semester_id' => $this->semester->id,
        'kode' => 'SubCPMK-RE',
        'deskripsi' => 'Sub untuk rantai CPL-CPMK.',
    ]);

    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'ASES-RE',
        'nama' => 'Asesmen Rencana Evaluasi',
        'bobot' => 100,
    ]);

    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $sub->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);

    $html = Livewire::test(ListKomponenPenilaians::class)->loadTable()->html();

    expect($html)
        ->toContain('Tabel Rencana Evaluasi')
        ->toContain('data-silogy="kode-keterangan-trigger"')
        ->toContain('data-jenis="CPL"')
        ->toContain('CPL-RE')
        ->toContain('Deskripsi CPL di rencana evaluasi.')
        ->toContain('data-jenis="CPMK"')
        ->toContain('CPMK-RE')
        ->toContain('Deskripsi CPMK di rencana evaluasi.');
});

it('membuat asesmen baru menghasilkan satu baris untuk mk dan semester terpilih, bukan satu per kelas', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(CreateKomponenPenilaian::class)
        ->fillForm([
            'evaluasi_id' => $this->evaluasi->id,
            'kode' => 'UTS',
            'nama' => 'UTS Teori',
            'bobot' => 100,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $komponens = KomponenPenilaian::query()->where('kode', 'UTS')->get();

    expect($komponens)->toHaveCount(1)
        ->and($komponens->first()->mk_id)->toBe($this->mk->id)
        ->and($komponens->first()->semester_id)->toBe($this->semester->id)
        ->and($komponens->first()->nama)->toBe('UTS Teori');
});

it('label field pada form adalah mata kuliah, bukan kelas mk', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(CreateKomponenPenilaian::class)
        ->assertSee('Mata Kuliah', escape: false)
        ->assertDontSee('Kelas MK', escape: false);
});

it('mengedit satu-satunya asesmen langsung berlaku untuk seluruh kelas mk terkait', function () {
    $uts = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 100,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(EditKomponenPenilaian::class, ['record' => $uts->getRouteKey()])
        ->assertSee('Mata Kuliah', escape: false)
        ->fillForm(['nama' => 'UTS Direvisi', 'kode' => 'UTS-1'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(KomponenPenilaian::query()->count())->toBe(1)
        ->and($uts->fresh())
        ->nama->toBe('UTS Direvisi')
        ->kode->toBe('UTS-1');
});

it('total bobot dihitung dari satu baris per kode, bukan dijumlah per kelas', function () {
    $utsA = KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 69,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(EditKomponenPenilaian::class, ['record' => $utsA->getRouteKey()])
        ->assertSee('Total bobot komponen pada mata kuliah dan semester ini: 69.00% dari 100% (kurang 31.00%).', escape: false);
});

it('tombol Normalisasi Bobot tampil bila total bobot belum 100%', function () {
    KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 0,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(ListKomponenPenilaians::class)->loadTable()
        ->assertSee('Total bobot komponen pada mata kuliah dan semester ini: 0% dari 100% (kurang 100%).', escape: false)
        ->assertSee('Normalisasi Bobot', escape: false)
        ->assertActionVisible('normalisasiBobot');
});

it('tombol Normalisasi Bobot disembunyikan bila total bobot sudah 100%', function () {
    KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 100,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(ListKomponenPenilaians::class)->loadTable()
        ->assertSee('sudah pas 100%', escape: false)
        ->assertActionHidden('normalisasiBobot');
});

it('banner bobot dan card rencana evaluasi tidak tampil jika komponen semester belum diisi', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(ListKomponenPenilaians::class)->loadTable()
        ->assertDontSee('Total bobot komponen pada mata kuliah dan semester ini', escape: false)
        ->assertDontSee('Tabel Rencana Evaluasi', escape: false)
        ->assertDontSee('Normalisasi Bobot', escape: false);
});

it('card Tabel Rencana Evaluasi tampil setelah komponen penilaian semester diisi', function () {
    KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $this->semester->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 100,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(ListKomponenPenilaians::class)->loadTable()
        ->assertSee('Tabel Rencana Evaluasi', escape: false);
});

it('mengimpor satu baris asesmen menghasilkan satu komponen yang dipakai bersama semua kelas mk (regresi 4x duplikat)', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    // Header "Impor massal" hanya tampil bila sudah ada komponen; tabel kosong
    // memakai empty-state action dengan nama yang sama.
    Livewire::test(ListKomponenPenilaians::class)
        ->callAction(\Filament\Actions\Testing\TestAction::make('bulkImport')->table(), data: [
            'import_mk_id' => $this->mk->id,
            'import_semester_id' => $this->semester->id,
            'rows' => 'Asesmen01|Kuis Konseptual|100|Quiz|',
        ])
        ->assertHasNoActionErrors();

    $komponens = KomponenPenilaian::query()->where('kode', 'Asesmen01')->get();

    expect($komponens)->toHaveCount(1)
        ->and($komponens->first()->mk_id)->toBe($this->mk->id)
        ->and($komponens->first()->semester_id)->toBe($this->semester->id);
});

it('mengosongkan preview import dari semester lain saat modal ditutup', function () {
    $semesterSumber = Semester::query()->create([
        'kode' => '20991',
        'nama' => 'Semester Sumber Uji',
        'tahun_mulai' => 2099,
        'tahun_selesai' => 2100,
        'jenis' => 'ganjil',
        'status_aktif' => false,
    ]);

    KomponenPenilaian::query()->create([
        'mk_id' => $this->mk->id,
        'semester_id' => $semesterSumber->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'SUMBER-01',
        'nama' => 'Kuis sumber',
        'bobot' => 100,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    $halaman = Livewire::test(ListKomponenPenilaians::class)
        ->set('salinAntarSemesterSumberLive', $semesterSumber->id);

    expect($halaman->get('salinAntarSemesterSumberLive'))->toBe($semesterSumber->id);

    $halaman
        ->call('unmountAction')
        ->assertSet('salinAntarSemesterSumberLive', null);
});

it('mengosongkan preview impor massal asesmen saat modal ditutup', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    $halaman = Livewire::test(ListKomponenPenilaians::class)
        ->mountAction('bulkImport')
        ->set('importMassalRowsLive', 'Asesmen01|Kuis Konseptual|50|Quiz|');

    expect($halaman->get('importMassalRowsLive'))->toContain('Asesmen01');

    $halaman->call('unmountAction');

    expect($halaman->get('importMassalRowsLive'))->toBe('');
});
