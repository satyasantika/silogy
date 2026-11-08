<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Filament\Pages\AnalisisMkProdi;
use App\Modules\Kurikulum\Filament\Pages\GrafikCpl;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Services\AnalisisMkProdiService;
use App\Modules\Kurikulum\Services\TrenCplSemesterService;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->dosen = User::query()->where('username', 'dosen')->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Tren',
        'tahun' => 2024,
        'is_active' => true,
        'target_capaian_lulusan' => 70,
    ]);

    $this->cpl = Cpl::factory()->forKurikulum($this->kurikulum)->create(['kode' => 'CPL-01']);
    $cplBok = CplBok::query()->create([
        'cpl_id' => $this->cpl->id,
        'bok_id' => Bok::factory()->forKurikulum($this->kurikulum)->create()->id,
        'bobot' => 100,
    ]);

    // Dua MK penyumbang CPL-01 dengan SKS & bobot sama → kontribusi 50/50.
    foreach (['mk1' => 'MK Satu', 'mk2' => 'MK Dua'] as $kunci => $nama) {
        $mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
            'nama' => $nama, 'sks' => 2, 'sks_teori' => 2, 'sks_praktik' => 0, 'sks_lapangan' => 0,
        ]);
        CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
        $this->{$kunci} = $mk;
        $this->{$kunci.'Unit'} = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create([
            'kurikulum_id' => $this->kurikulum->id,
            'is_active' => true,
        ]);
    }

    $this->semester = fn (string $kode): Semester => Semester::query()->where('kode', $kode)->firstOrFail();
});

function nilaiCplTren(object $ctx, MkUnit $mkUnit, Semester $semester, Mahasiswa $mahasiswa, float $nilai): void
{
    $kelas = KelasMk::query()->firstOrCreate(
        ['mk_unit_id' => $mkUnit->id, 'semester_id' => $semester->id, 'kode_kelas' => 'A'],
        ['dosen_pengampu_id' => $ctx->dosen->id],
    );
    $kmm = KelasMkMahasiswa::query()->firstOrCreate([
        'kelas_mk_id' => $kelas->id,
        'mahasiswa_id' => $mahasiswa->id,
    ]);

    HasilCplMk::query()->create([
        'cpl_id' => $ctx->cpl->id,
        'mk_unit_id' => $mkUnit->id,
        'kelas_mk_mahasiswa_id' => $kmm->id,
        'semester_id' => $semester->id,
        'nilai_akhir' => $nilai,
    ]);
}

it('menghitung ketercapaian cpl per semester dengan kontribusi dibagi ulang di antara mk yang dinilai', function () {
    $ganjil = ($this->semester)('20241');
    $genap = ($this->semester)('20242');

    $budi = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $sari = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);

    // Ganjil: hanya MK Satu yang ditawarkan. Tanpa pembagian ulang, nilai
    // 80 akan terbaca 40 (80 × 50/100) — anjlok palsu.
    nilaiCplTren($this, $this->mk1Unit, $ganjil, $budi, 80);
    nilaiCplTren($this, $this->mk1Unit, $ganjil, $sari, 60);

    // Genap: Budi menempuh dua MK (50/50) → 70; Sari hanya MK Dua → 90.
    nilaiCplTren($this, $this->mk1Unit, $genap, $budi, 60);
    nilaiCplTren($this, $this->mk2Unit, $genap, $budi, 80);
    nilaiCplTren($this, $this->mk2Unit, $genap, $sari, 90);

    $mkUnitIds = app(AnalisisMkProdiService::class)->mkUnitIdsUntukKurikulum($this->kurikulum);
    $tren = app(TrenCplSemesterService::class)->tren($this->kurikulum, $mkUnitIds);

    expect($tren['target'])->toBe(70)
        ->and(array_column($tren['semesters'], 'label'))->toBe(['Ganjil 2024/2025', 'Genap 2024/2025'])
        ->and($tren['cpl'])->toHaveCount(1)
        ->and($tren['cpl'][0]['kode'])->toBe('CPL-01')
        ->and($tren['cpl'][0]['data'])->toBe([70.0, 80.0]);
});

it('versi satu mk hanya memakai nilai dari mk tersebut', function () {
    $ganjil = ($this->semester)('20241');
    $genap = ($this->semester)('20242');
    $budi = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);

    nilaiCplTren($this, $this->mk1Unit, $ganjil, $budi, 50);
    nilaiCplTren($this, $this->mk2Unit, $ganjil, $budi, 100);
    nilaiCplTren($this, $this->mk1Unit, $genap, $budi, 75);

    $mkUnitIds = app(AnalisisMkProdiService::class)->mkUnitIdsUntukKurikulum($this->kurikulum);
    $tren = app(TrenCplSemesterService::class)->tren($this->kurikulum, $mkUnitIds, $this->mk1->id);

    expect($tren['cpl'][0]['data'])->toBe([50.0, 75.0]);
});

it('cpl tanpa nilai pada suatu semester bernilai null di semester itu', function () {
    $ganjil = ($this->semester)('20241');
    $genap = ($this->semester)('20242');
    $budi = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);

    nilaiCplTren($this, $this->mk1Unit, $genap, $budi, 65);

    $cpl2 = Cpl::factory()->forKurikulum($this->kurikulum)->create(['kode' => 'CPL-02']);
    $cplBok2 = CplBok::query()->create([
        'cpl_id' => $cpl2->id,
        'bok_id' => Bok::factory()->forKurikulum($this->kurikulum)->create()->id,
        'bobot' => 100,
    ]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok2->id, 'mk_id' => $this->mk2->id, 'bobot' => 100]);
    $kmm = KelasMkMahasiswa::query()->firstOrCreate([
        'kelas_mk_id' => KelasMk::query()->create([
            'mk_unit_id' => $this->mk2Unit->id, 'semester_id' => $ganjil->id, 'kode_kelas' => 'B', 'dosen_pengampu_id' => $this->dosen->id,
        ])->id,
        'mahasiswa_id' => $budi->id,
    ]);
    HasilCplMk::query()->create([
        'cpl_id' => $cpl2->id, 'mk_unit_id' => $this->mk2Unit->id, 'kelas_mk_mahasiswa_id' => $kmm->id,
        'semester_id' => $ganjil->id, 'nilai_akhir' => 88,
    ]);

    $mkUnitIds = app(AnalisisMkProdiService::class)->mkUnitIdsUntukKurikulum($this->kurikulum);
    $tren = app(TrenCplSemesterService::class)->tren($this->kurikulum, $mkUnitIds);

    $perKode = collect($tren['cpl'])->keyBy('kode');

    expect($perKode['CPL-01']['data'])->toBe([null, 65.0])
        ->and($perKode['CPL-02']['data'])->toBe([88.0, null]);
});

it('halaman grafik cpl pimpinan menampilkan grafik tren antarsemester beserta tabel datanya', function () {
    $budi = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);
    nilaiCplTren($this, $this->mk1Unit, ($this->semester)('20241'), $budi, 72);
    nilaiCplTren($this, $this->mk1Unit, ($this->semester)('20242'), $budi, 81);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::query()->where('username', 'kaprodi')->firstOrFail());
    ActiveRole::set('Pimpinan');
    KurikulumTerpilih::set($this->kurikulum->id);

    Livewire::test(GrafikCpl::class)
        ->assertSuccessful()
        ->assertSeeHtml('data-silogy="grafik-tren-cpl"')
        ->assertSee('Tren Ketercapaian CPL Antarsemester')
        ->assertSee('Ganjil 2024/2025')
        ->assertSee('Genap 2024/2025')
        ->assertSee('CPL-01');
});

it('grafik tren memberi keterangan bila baru satu semester yang dinilai', function () {
    $budi = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);
    nilaiCplTren($this, $this->mk1Unit, ($this->semester)('20241'), $budi, 72);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
    ActiveRole::set('Tim Kurikulum');
    KurikulumTerpilih::set($this->kurikulum->id);

    Livewire::test(AnalisisMkProdi::class)
        ->assertSuccessful()
        ->assertSeeHtml('data-silogy="grafik-tren-cpl"')
        ->assertSee('Tren muncul setelah minimal 2 semester dinilai');
});
