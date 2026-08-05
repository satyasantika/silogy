<?php

use App\Models\User;
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
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Services\AnalisisMkProdiService;
use App\Modules\Kurikulum\Services\IpkKumulatifService;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Mahasiswa\Support\AngkatanDariNim;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

/**
 * Membangun satu MK dengan satu CPL (bobot penuh di setiap tahap) dan dua
 * mahasiswa dari angkatan berbeda, masing-masing dengan nilai akhir yang
 * sudah dihitung penuh lewat rantai kalkulator (bukan sekadar hasil_cpl_mk
 * ditulis manual) — supaya sinkronkanKalkulasiProdi() benar-benar teruji.
 *
 * @return array{kurikulum: Kurikulum, cpl: Cpl}
 */
function siapkanFixtureDuaAngkatan(AcademicUnit $prodi, Kurikulum $kurikulum, User $dosen): array
{
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create(['kode' => 'KU21513001', 'is_active' => true]);

    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $dosen->id,
    ]);

    $mahasiswaLama = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id, 'angkatan' => '2023']);
    $mahasiswaBaru = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id, 'angkatan' => '2024']);

    $kmmLama = KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelas->id, 'mahasiswa_id' => $mahasiswaLama->id]);
    $kmmBaru = KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelas->id, 'mahasiswa_id' => $mahasiswaBaru->id]);

    $cpmk = Cpmk::factory()->forMk($mk)->create();
    $cpl = Cpl::factory()->forAcademicUnit($prodi)->create(['kode' => 'CPL02']);
    $bok = Bok::factory()->forAcademicUnit($prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create(['semester_id' => $semester->id]);

    $evaluasi = Evaluasi::query()->where('kode', 'quiz')->firstOrFail();
    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'Asesmen01',
        'nama' => 'Kuis',
        'bobot' => 100,
    ]);
    $skp = SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);

    NilaiMahasiswa::query()->create(['subcpmk_komponenpenilaian_id' => $skp->id, 'kelas_mk_mahasiswa_id' => $kmmLama->id, 'nilai' => 90]);
    NilaiMahasiswa::query()->create(['subcpmk_komponenpenilaian_id' => $skp->id, 'kelas_mk_mahasiswa_id' => $kmmBaru->id, 'nilai' => 50]);

    // RecalkulasiCplJob otomatis terpicu saat NilaiMahasiswa disimpan (queue
    // 'sync' di lingkungan test) — dihapus di sini supaya test benar-benar
    // membuktikan sinkronkanKalkulasiProdi() (bukan job queue) yang mengisi
    // ulang hasil_cpl_mk, konsisten dengan asumsi queue mati di produksi.
    HasilCplMk::query()->delete();

    return compact('kurikulum', 'cpl');
}

/**
 * Satu MK penyumbang CPL yang sudah ada, lengkap dengan rantai
 * CPMK → Sub-CPMK → komponen penilaian → nilai mahasiswa, supaya
 * hasil_cpl_mk terisi lewat kalkulator sesungguhnya. Dipakai untuk menguji
 * ketercapaian CPL tertimbang, yang butuh lebih dari satu MK per CPL dengan
 * bobot/SKS berbeda.
 *
 * @param  array<string, int|float>  $nilaiPerMahasiswaId  mahasiswa_id => nilai
 */
function buatMkPenyumbangCpl(
    AcademicUnit $prodi,
    User $dosen,
    CplBok $cplBok,
    string $nama,
    string $kodeMkUnit,
    float $bobotCplMk,
    array $nilaiPerMahasiswaId,
    int $sks = 2,
): Mk {
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $mk = Mk::factory()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => $nama,
        'sks_teori' => $sks,
        'sks_praktik' => 0,
        'sks_lapangan' => 0,
        'sks' => $sks,
    ]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create([
        'kode' => $kodeMkUnit,
        'is_active' => true,
    ]);

    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $dosen->id,
    ]);

    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => $bobotCplMk]);
    $cpmk = Cpmk::factory()->forMk($mk)->create();
    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create(['semester_id' => $semester->id]);

    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => Evaluasi::query()->where('kode', 'quiz')->value('id'),
        'kode' => 'Asesmen01',
        'nama' => 'Kuis',
        'bobot' => 100,
    ]);
    $skp = SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);

    foreach ($nilaiPerMahasiswaId as $mahasiswaId => $nilai) {
        $kmm = KelasMkMahasiswa::query()->create([
            'kelas_mk_id' => $kelas->id,
            'mahasiswa_id' => $mahasiswaId,
        ]);

        NilaiMahasiswa::query()->create([
            'subcpmk_komponenpenilaian_id' => $skp->id,
            'kelas_mk_mahasiswa_id' => $kmm->id,
            'nilai' => $nilai,
        ]);
    }

    return $mk;
}

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->kaprodi = User::query()->where('username', 'kaprodi')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->dosen = User::query()->where('username', 'dosen')->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Analisis MK Prodi',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    KurikulumTerpilih::set($this->kurikulum->id);
});

it('kaprodi (Pimpinan prodi) bisa mengakses halaman Analisis MK Prodi', function () {
    $this->actingAs($this->kaprodi);

    expect(AnalisisMkProdi::canAccess())->toBeTrue();
});

it('timkur (Tim Kurikulum prodi) bisa mengakses halaman Analisis MK Prodi', function () {
    $this->actingAs($this->timkur);

    expect(AnalisisMkProdi::canAccess())->toBeTrue();
});

it('dosen pengampu tanpa penugasan pimpinan/tim kurikulum tidak bisa mengakses', function () {
    $this->actingAs($this->dosen);

    expect(AnalisisMkProdi::canAccess())->toBeFalse();
});

it('pimpinan/tim kurikulum tanpa penugasan prodi manapun tidak bisa mengakses', function () {
    $pimpinanTanpaUnit = User::factory()->create();
    $pimpinanTanpaUnit->assignRole('Pimpinan');

    $this->actingAs($pimpinanTanpaUnit);

    expect(AnalisisMkProdi::canAccess())->toBeFalse();
});

it('analisis mengikuti kurikulum terpilih di session', function () {
    $this->actingAs($this->timkur);

    $kurikulumAktif = $this->kurikulum;
    $kurikulumDraft = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Draft Analisis',
        'tahun' => 2025,
        'is_active' => false,
    ]);

    KurikulumTerpilih::set($kurikulumDraft->id);

    $test = Livewire::test(AnalisisMkProdi::class)
        ->assertSee('Kurikulum yang dikerjakan', escape: false)
        ->assertSeeHtml('data-silogy="banner-header-panel"')
        ->assertSee('Pemetaan Rencana Asesmen CPL', escape: false)
        ->assertDontSee('Seluruh pemetaan, hasil asesmen, dan grafik CPL di bawah dihitung dari kurikulum prodi ini', escape: false)
        ->assertDontSee('Pilih Program Studi', escape: false);

    expect($test->get('kurikulum')?->id)->toBe($kurikulumDraft->id)
        ->and($kurikulumAktif->is_active)->toBeTrue();
});

it('menormalisasi kontribusi MK per CPL jadi tepat 100% dan tetap membawa bobot mentahnya', function () {
    $this->actingAs($this->kaprodi);
    KurikulumTerpilih::set($this->kurikulum->id);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create([
        'kode' => 'CPL01',
        'deskripsi' => 'Menunjukkan sikap bertakwa dan menjunjung etika akademik.',
    ]);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);

    $mkAgama = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Agama',
        'sks_teori' => 2,
        'sks_praktik' => 0,
        'sks_lapangan' => 0,
        'sks' => 2,
    ]);
    MkUnit::factory()->forMk($mkAgama)->forAcademicUnit($this->prodi)->create([
        'kode' => 'KU21511001',
        'is_active' => true,
    ]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mkAgama->id, 'bobot' => 9.30]);

    $mkPancasila = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Pancasila',
        'sks_teori' => 2,
        'sks_praktik' => 0,
        'sks_lapangan' => 0,
        'sks' => 2,
    ]);
    MkUnit::factory()->forMk($mkPancasila)->forAcademicUnit($this->prodi)->create([
        'kode' => 'KU21512001',
        'is_active' => true,
    ]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mkPancasila->id, 'bobot' => 13.95]);

    // Bobot mentah 9,30 + 13,95; SKS sama (2) → bobot×SKS 18,6 : 27,9 → porsi 40% : 60%.
    $test = Livewire::test(AnalisisMkProdi::class)
        ->assertSee('CPL01', escape: false)
        ->assertSee('Agama (KU21511001)', escape: false)
        ->assertSee('Pancasila (KU21512001)', escape: false)
        ->assertSee('40%', escape: false)
        ->assertSee('60%', escape: false)
        ->assertSee('Bobot tersimpan pada matriks', escape: false);

    $pemetaan = $test->get('pemetaanCplMk');

    expect($pemetaan)->toHaveCount(1)
        ->and($pemetaan[0]['cpl_kode'])->toBe('CPL01')
        ->and($pemetaan[0]['mk_rows'])->toHaveCount(2)
        ->and($pemetaan[0]['mk_rows'][0]['nama'])->toBe('Agama')
        ->and($pemetaan[0]['mk_rows'][0]['sks'])->toBe(2)
        ->and($pemetaan[0]['mk_rows'][0]['kontribusi'])->toBe(40.0)
        ->and($pemetaan[0]['mk_rows'][0]['bobot_mentah'])->toBe(9.3)
        ->and($pemetaan[0]['mk_rows'][1]['kontribusi'])->toBe(60.0)
        ->and($pemetaan[0]['mk_rows'][1]['bobot_mentah'])->toBe(13.95)
        ->and(collect($pemetaan[0]['mk_rows'])->sum('kontribusi'))->toBe(100.0);
});

it('membulatkan kontribusi ke 2 desimal dengan sisa dikoreksi agar total per CPL tetap 100%', function () {
    $this->actingAs($this->kaprodi);
    KurikulumTerpilih::set($this->kurikulum->id);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL07']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);

    // Tiga MK berbobot sama — 100/3 tidak habis dibagi, jadi salah satu MK
    // harus menyerap sisa pembulatan (33,34) supaya totalnya tetap 100.
    foreach (['Kalkulus', 'Fisika', 'Kimia'] as $urutan => $nama) {
        $mk = Mk::factory()->create([
            'academic_unit_id' => $this->prodi->id,
            'nama' => $nama,
            'sks_teori' => 2, 'sks_praktik' => 0, 'sks_lapangan' => 0, 'sks' => 2,
        ]);
        MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create([
            'kode' => 'KU2152100'.$urutan,
            'is_active' => true,
        ]);
        CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 10]);
    }

    $pemetaan = app(AnalisisMkProdiService::class)->pemetaanCplMk($this->kurikulum);
    $kontribusi = collect($pemetaan[0]['mk_rows'])->pluck('kontribusi');

    expect($kontribusi->sum())->toBe(100.0)
        ->and($kontribusi->sort()->values()->all())->toBe([33.33, 33.33, 33.34]);
});

it('memperhitungkan SKS saat menormalisasi kontribusi MK per CPL', function () {
    $this->actingAs($this->kaprodi);
    KurikulumTerpilih::set($this->kurikulum->id);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL10']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);

    // Bobot mentah sama (10), SKS berbeda (2 vs 3) → bobot×SKS 20 : 30 → porsi 40% : 60%.
    // Tanpa SKS keduanya akan 50%–50%.
    $mkDuaSks = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Aljabar',
        'sks_teori' => 2, 'sks_praktik' => 0, 'sks_lapangan' => 0, 'sks' => 2,
    ]);
    MkUnit::factory()->forMk($mkDuaSks)->forAcademicUnit($this->prodi)->create([
        'kode' => 'KU21524001',
        'is_active' => true,
    ]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mkDuaSks->id, 'bobot' => 10]);

    $mkTigaSks = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Statistika',
        'sks_teori' => 3, 'sks_praktik' => 0, 'sks_lapangan' => 0, 'sks' => 3,
    ]);
    MkUnit::factory()->forMk($mkTigaSks)->forAcademicUnit($this->prodi)->create([
        'kode' => 'KU21524002',
        'is_active' => true,
    ]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mkTigaSks->id, 'bobot' => 10]);

    $pemetaan = app(AnalisisMkProdiService::class)->pemetaanCplMk($this->kurikulum);
    $mkRows = collect($pemetaan[0]['mk_rows'])->keyBy('nama');

    expect($mkRows['Aljabar']['kontribusi'])->toBe(40.0)
        ->and($mkRows['Aljabar']['bobot_mentah'])->toBe(10.0)
        ->and($mkRows['Aljabar']['sks'])->toBe(2)
        ->and($mkRows['Statistika']['kontribusi'])->toBe(60.0)
        ->and($mkRows['Statistika']['bobot_mentah'])->toBe(10.0)
        ->and($mkRows['Statistika']['sks'])->toBe(3)
        ->and($mkRows->sum('kontribusi'))->toBe(100.0);
});

it('menampilkan CPL milik unit induk bila MK-nya diadaptasi lewat penawaran MK prodi', function () {
    $this->actingAs($this->kaprodi);

    $fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $kurikulumFakultas = Kurikulum::query()->create([
        'academic_unit_id' => $fakultas->id,
        'nama' => 'Kurikulum Fakultas Uji Rollup',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $cplFakultas = Cpl::factory()->forKurikulum($kurikulumFakultas)->create(['kode' => 'CPL-FAK-01']);
    $bokFakultas = Bok::factory()->forKurikulum($kurikulumFakultas)->create();
    $cplBokFakultas = CplBok::query()->create(['cpl_id' => $cplFakultas->id, 'bok_id' => $bokFakultas->id, 'bobot' => 100]);

    $mkFakultas = Mk::factory()->forKurikulum($kurikulumFakultas)->create([
        'nama' => 'MK Adaptasi Fakultas',
        'sks_teori' => 2, 'sks_praktik' => 0, 'sks_lapangan' => 0, 'sks' => 2,
    ]);
    CplMk::query()->create(['cpl_bok_id' => $cplBokFakultas->id, 'mk_id' => $mkFakultas->id, 'bobot' => 100]);

    // Prodi ini "menawarkan" MK milik fakultas lewat mk_units miliknya sendiri.
    MkUnit::factory()->forMk($mkFakultas)->forAcademicUnit($this->prodi)->create([
        'kurikulum_id' => $this->kurikulum->id,
        'kode' => 'ADAPT-FAK-01',
        'is_active' => true,
    ]);

    $test = Livewire::test(AnalisisMkProdi::class)
        ->assertSee('CPL-FAK-01', escape: false)
        ->assertSee('MK Adaptasi Fakultas', escape: false);

    $pemetaan = $test->get('pemetaanCplMk');

    expect(collect($pemetaan)->pluck('cpl_kode'))->toContain('CPL-FAK-01');
});

it('menampilkan pesan kosong bila belum ada CPL yang dibebankan', function () {
    $this->actingAs($this->kaprodi);
    KurikulumTerpilih::set($this->kurikulum->id);

    Livewire::test(AnalisisMkProdi::class)
        ->assertSee('Belum ada CPL yang dibebankan', escape: false);
});

it('sinkronkanKalkulasiProdi mengisi ulang hasil_cpl_mk lewat kalkulasi sinkron, bukan queue', function () {
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);
    siapkanFixtureDuaAngkatan($this->prodi, $this->kurikulum, $this->dosen);

    expect(HasilCplMk::query()->count())->toBe(0);

    app(AnalisisMkProdiService::class)->sinkronkanKalkulasiProdi($this->kurikulum);

    expect(HasilCplMk::query()->count())->toBe(2);
});

it('hasilAnalisisPerAngkatan mengelompokkan rerata dan N per angkatan mahasiswa', function () {
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);
    $this->actingAs($this->kaprodi);

    siapkanFixtureDuaAngkatan($this->prodi, $this->kurikulum, $this->dosen);

    $test = Livewire::test(AnalisisMkProdi::class);
    $hasilAnalisis = $test->get('hasilAnalisis');

    expect($hasilAnalisis['angkatan_list'])->toBe(['2023', '2024'])
        ->and($hasilAnalisis['pemetaan'])->toHaveCount(1);

    $mkRow = $hasilAnalisis['pemetaan'][0]['mk_rows'][0];

    expect($mkRow['per_angkatan']['2023']['rata_rata'])->toBe(90.0)
        ->and($mkRow['per_angkatan']['2023']['n'])->toBe(1)
        ->and($mkRow['per_angkatan']['2024']['rata_rata'])->toBe(50.0)
        ->and($mkRow['per_angkatan']['2024']['n'])->toBe(1)
        ->and($mkRow['rata_rata_keseluruhan'])->toBe(70.0);

    $ketercapaian = $hasilAnalisis['pemetaan'][0]['ketercapaian'];

    expect($ketercapaian['jumlah_mahasiswa'])->toBe(2)
        ->and($ketercapaian['rata_rata'])->toBe(70.0)
        ->and($ketercapaian['persentase_tercapai'])->toBe(50.0)
        ->and($ketercapaian['tercapai'])->toBeFalse();

    $test->assertSee('CPL tidak tercapai', escape: false)
        ->assertSeeHtml('n=1')
        ->assertDontSeeHtml('>Rerata</th>')
        ->assertDontSeeHtml('>N</th>');
});

it('hasilAnalisisPerAngkatan tetap menampilkan rerata dan ketercapaian bila angkatan mahasiswa kosong', function () {
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);
    $this->actingAs($this->kaprodi);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-NULL-ANG']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);

    $mahasiswa = Mahasiswa::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nim' => '249990001',
        'angkatan' => null,
    ]);

    buatMkPenyumbangCpl($this->prodi, $this->dosen, $cplBok, 'MK Tanpa Angkatan', 'KU21599001', 100, [$mahasiswa->id => 80]);

    $hasilAnalisis = Livewire::test(AnalisisMkProdi::class)->get('hasilAnalisis');
    $cplGroup = collect($hasilAnalisis['pemetaan'])->firstWhere('cpl_kode', 'CPL-NULL-ANG');

    expect($hasilAnalisis['angkatan_list'])->toBe([AngkatanDariNim::LABEL_TANPA_ANGKATAN])
        ->and($cplGroup['mk_rows'][0]['rata_rata_keseluruhan'])->toBe(80.0)
        ->and($cplGroup['mk_rows'][0]['per_angkatan'][AngkatanDariNim::LABEL_TANPA_ANGKATAN]['n'])->toBe(1)
        ->and($cplGroup['ketercapaian']['rata_rata'])->toBe(80.0)
        ->and($cplGroup['ketercapaian']['jumlah_mahasiswa'])->toBe(1);
});

it('menghitung ketercapaian CPL tertimbang menurut kontribusi MK, bukan rata-rata sederhana', function () {
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);
    $this->actingAs($this->kaprodi);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL08']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);

    $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'angkatan' => '2023']);

    // Bobot mentah 15 dan 35, SKS sama (2) → bobot×SKS 30 : 70 → porsi 30% : 70%.
    buatMkPenyumbangCpl($this->prodi, $this->dosen, $cplBok, 'Basis Data', 'KU21522001', 15, [$mahasiswa->id => 100]);
    buatMkPenyumbangCpl($this->prodi, $this->dosen, $cplBok, 'Pemrograman', 'KU21522002', 35, [$mahasiswa->id => 50]);

    $hasilAnalisis = Livewire::test(AnalisisMkProdi::class)->get('hasilAnalisis');
    $cplGroup = collect($hasilAnalisis['pemetaan'])->firstWhere('cpl_kode', 'CPL08');

    expect($cplGroup['mk_rows'][0]['kontribusi'])->toBe(30.0)
        ->and($cplGroup['mk_rows'][1]['kontribusi'])->toBe(70.0)
        // Tertimbang: (100 × 30 + 50 × 70) / 100 = 65, bukan (100 + 50) / 2 = 75.
        ->and($cplGroup['ketercapaian']['rata_rata'])->toBe(65.0)
        ->and($cplGroup['ketercapaian']['jumlah_mahasiswa'])->toBe(1)
        ->and($cplGroup['ketercapaian']['tercapai'])->toBeFalse();
});

it('menekan ketercapaian sesuai kontribusi MK yang belum ditempuh (tanpa renormalisasi)', function () {
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);
    $this->actingAs($this->kaprodi);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL09']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);

    $mahasiswaLengkap = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'angkatan' => '2023']);
    $mahasiswaSebagian = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'angkatan' => '2024']);

    buatMkPenyumbangCpl($this->prodi, $this->dosen, $cplBok, 'Basis Data', 'KU21523001', 30, [
        $mahasiswaLengkap->id => 100,
    ]);
    buatMkPenyumbangCpl($this->prodi, $this->dosen, $cplBok, 'Pemrograman', 'KU21523002', 70, [
        $mahasiswaLengkap->id => 50,
        $mahasiswaSebagian->id => 80,
    ]);

    $hasilAnalisis = Livewire::test(AnalisisMkProdi::class)->get('hasilAnalisis');
    $ketercapaian = collect($hasilAnalisis['pemetaan'])->firstWhere('cpl_kode', 'CPL09')['ketercapaian'];

    // Lengkap: (100×30 + 50×70) / 100 = 65. Sebagian (hanya MK 70%): 80×70 / 100 = 56.
    // Rerata CPL = (65 + 56) / 2 = 60,5 — belum ada yang ≥ target 75.
    expect($ketercapaian['jumlah_mahasiswa'])->toBe(2)
        ->and($ketercapaian['rata_rata'])->toBe(60.5)
        ->and($ketercapaian['persentase_tercapai'])->toBe(0.0)
        ->and($ketercapaian['tercapai'])->toBeFalse();
});

it('menandai baris MK success/warning menurut rerata vs target, tanpa tanda bila belum dinilai', function () {
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);
    $this->actingAs($this->kaprodi);

    $this->kurikulum->update(['target_capaian_lulusan' => 75]);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL-TINT']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);

    $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'angkatan' => '2023']);

    buatMkPenyumbangCpl($this->prodi, $this->dosen, $cplBok, 'MK Lulus Target', 'KU21524001', 40, [$mahasiswa->id => 80]);
    buatMkPenyumbangCpl($this->prodi, $this->dosen, $cplBok, 'MK Di Bawah Target', 'KU21524002', 40, [$mahasiswa->id => 60]);

    $mkBelum = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'MK Belum Dinilai',
        'sks_teori' => 2,
        'sks_praktik' => 0,
        'sks_lapangan' => 0,
        'sks' => 2,
    ]);
    MkUnit::factory()->forMk($mkBelum)->forAcademicUnit($this->prodi)->create(['kode' => 'KU21524003', 'is_active' => true]);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mkBelum->id, 'bobot' => 20]);

    Livewire::test(AnalisisMkProdi::class)
        ->assertSeeHtml('data-silogy-mk-capaian="success"')
        ->assertSeeHtml('data-silogy-mk-capaian="warning"')
        ->assertSee('MK Belum Dinilai', escape: false)
        ->assertDontSeeHtml('hasil-'.$cpl->id.'-'.$mkBelum->id.'" data-silogy-mk-capaian');
});

it('menampilkan "menunggu selesai penilaian" bila CPL belum punya hasil kalkulasi', function () {
    $this->actingAs($this->kaprodi);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL03']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create(['kode' => 'KU21514001']);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);

    Livewire::test(AnalisisMkProdi::class)
        ->assertSee('Menunggu selesai penilaian', escape: false);
});

it('radarPerCpl menyusun label dan data rerata per MK penyumbang CPL', function () {
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);
    $this->actingAs($this->kaprodi);

    siapkanFixtureDuaAngkatan($this->prodi, $this->kurikulum, $this->dosen);

    $test = Livewire::test(AnalisisMkProdi::class);
    $radarPerCpl = $test->get('radarPerCpl');

    expect($radarPerCpl)->toHaveCount(1)
        ->and($radarPerCpl[0]['cpl_kode'])->toBe('CPL02')
        ->and($radarPerCpl[0]['ada_data'])->toBeTrue()
        ->and($radarPerCpl[0]['labels'])->toHaveCount(1)
        ->and($radarPerCpl[0]['data'][0])->toBe(70.0);
});

it('radarPerCpl menandai ada_data false bila CPL belum punya hasil kalkulasi', function () {
    $this->actingAs($this->kaprodi);

    $cpl = Cpl::factory()->forAcademicUnit($this->prodi)->create(['kode' => 'CPL04']);
    $bok = Bok::factory()->forAcademicUnit($this->prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create(['kode' => 'KU21515001']);
    CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);

    $test = Livewire::test(AnalisisMkProdi::class);
    $radarPerCpl = $test->get('radarPerCpl');

    expect($radarPerCpl[0]['ada_data'])->toBeFalse();

    $test->assertSee('Grafik belum dapat ditampilkan karena mata kuliah pada CPL ini belum dinilai', escape: false);
});

it('roster IPK kumulatif menghitung SKS dikontrak, nilai rerata, dan IPK ber-SKS dari huruf asli tiap kelas', function () {
    $this->seed(SemesterSeeder::class);
    $this->actingAs($this->kaprodi);

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $mahasiswa = Mahasiswa::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nim' => '242151100001',
        'nama' => 'Uji Roster',
    ]);

    $mkA = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'sks_teori' => 3, 'sks_praktik' => 0, 'sks_lapangan' => 0, 'sks' => 3]);
    $mkUnitA = MkUnit::factory()->forMk($mkA)->forAcademicUnit($this->prodi)->create([
        'kurikulum_id' => $this->kurikulum->id,
        'kode' => 'KU21516001',
    ]);
    $kelasA = KelasMk::query()->create(['mk_unit_id' => $mkUnitA->id, 'semester_id' => $semester->id, 'kode_kelas' => 'A', 'dosen_pengampu_id' => $this->dosen->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasA->id, 'mahasiswa_id' => $mahasiswa->id, 'nilai_angka' => 90, 'nilai_huruf' => 'A']);

    $mkB = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'sks_teori' => 2, 'sks_praktik' => 0, 'sks_lapangan' => 0, 'sks' => 2]);
    $mkUnitB = MkUnit::factory()->forMk($mkB)->forAcademicUnit($this->prodi)->create([
        'kurikulum_id' => $this->kurikulum->id,
        'kode' => 'KU21517001',
    ]);
    $kelasB = KelasMk::query()->create(['mk_unit_id' => $mkUnitB->id, 'semester_id' => $semester->id, 'kode_kelas' => 'A', 'dosen_pengampu_id' => $this->dosen->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasB->id, 'mahasiswa_id' => $mahasiswa->id, 'nilai_angka' => 60, 'nilai_huruf' => 'C+']);

    $roster = app(IpkKumulatifService::class)->rosterKurikulum($this->kurikulum);
    $baris = collect($roster)->firstWhere('mahasiswa_id', $mahasiswa->id);

    expect($baris['sks_dikontrak'])->toBe(5)
        ->and($baris['nilai_angka'])->toBe(75.0)
        ->and($baris['nilai_huruf'])->toBe('B+')
        ->and($baris['bobot_huruf'])->toBe(3.3)
        ->and($baris['ipk'])->toBe(3.32);

    Livewire::test(AnalisisMkProdi::class)
        ->assertSee('242151100001', escape: false)
        ->assertSee('3.32', escape: false);
});

it('roster IPK hanya menampilkan mahasiswa yang mengontrak MK pada kurikulum terpilih', function () {
    $this->seed(SemesterSeeder::class);
    $this->actingAs($this->kaprodi);

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $mahasiswaKontrak = Mahasiswa::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nim' => '242151100010',
        'nama' => 'Mahasiswa Kontrak Kurikulum',
    ]);
    $mahasiswaTanpaKontrak = Mahasiswa::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nim' => '242151100011',
        'nama' => 'Mahasiswa Tanpa Kontrak',
    ]);

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Lain Roster',
        'tahun' => 2019,
        'is_active' => false,
    ]);

    $mahasiswaHanyaKurikulumLain = Mahasiswa::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nim' => '242151100012',
        'nama' => 'Mahasiswa Kurikulum Lain',
    ]);

    $mkAktif = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'sks_teori' => 2, 'sks_praktik' => 0, 'sks_lapangan' => 0, 'sks' => 2,
    ]);
    $mkUnitAktif = MkUnit::factory()->forMk($mkAktif)->forKurikulum($this->kurikulum)->create(['kode' => 'ROSTER-AKTIF']);
    $kelasAktif = KelasMk::query()->create([
        'mk_unit_id' => $mkUnitAktif->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);
    KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasAktif->id,
        'mahasiswa_id' => $mahasiswaKontrak->id,
        'nilai_angka' => 85,
        'nilai_huruf' => 'A',
    ]);

    $mkLain = Mk::factory()->forKurikulum($kurikulumLain)->create([
        'sks_teori' => 3, 'sks_praktik' => 0, 'sks_lapangan' => 0, 'sks' => 3,
    ]);
    $mkUnitLain = MkUnit::factory()->forMk($mkLain)->forKurikulum($kurikulumLain)->create(['kode' => 'ROSTER-LAIN']);
    $kelasLain = KelasMk::query()->create([
        'mk_unit_id' => $mkUnitLain->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);
    KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasLain->id,
        'mahasiswa_id' => $mahasiswaHanyaKurikulumLain->id,
        'nilai_angka' => 70,
        'nilai_huruf' => 'B',
    ]);

    $roster = collect(app(IpkKumulatifService::class)->rosterKurikulum($this->kurikulum));

    expect($roster)->toHaveCount(1)
        ->and($roster->first()['mahasiswa_id'])->toBe($mahasiswaKontrak->id)
        ->and($roster->firstWhere('mahasiswa_id', $mahasiswaTanpaKontrak->id))->toBeNull()
        ->and($roster->firstWhere('mahasiswa_id', $mahasiswaHanyaKurikulumLain->id))->toBeNull();

    Livewire::test(AnalisisMkProdi::class)
        ->assertSee('242151100010', escape: false)
        ->assertSee('Mahasiswa yang mengontrak mata kuliah pada kurikulum yang dikerjakan', escape: false)
        ->assertDontSee('242151100011', escape: false)
        ->assertDontSee('242151100012', escape: false);
});

it('roster IPK dan grafik CPL mahasiswa mengabaikan kelas dari kurikulum lain', function () {
    $this->seed(SemesterSeeder::class);
    $this->actingAs($this->kaprodi);

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Lama Analisis',
        'tahun' => 2020,
        'is_active' => false,
    ]);

    $mahasiswa = Mahasiswa::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nim' => '242151100099',
        'nama' => 'Uji Scope Kurikulum',
    ]);

    $mkAktif = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'sks_teori' => 2, 'sks_praktik' => 0, 'sks_lapangan' => 0, 'sks' => 2,
    ]);
    $mkUnitAktif = MkUnit::factory()->forMk($mkAktif)->forKurikulum($this->kurikulum)->create(['kode' => 'AKTIF-01']);
    $kelasAktif = KelasMk::query()->create([
        'mk_unit_id' => $mkUnitAktif->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);
    $kmmAktif = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasAktif->id,
        'mahasiswa_id' => $mahasiswa->id,
        'nilai_angka' => 80,
        'nilai_huruf' => 'A-',
    ]);

    $mkLama = Mk::factory()->forKurikulum($kurikulumLain)->create([
        'sks_teori' => 4, 'sks_praktik' => 0, 'sks_lapangan' => 0, 'sks' => 4,
    ]);
    $mkUnitLama = MkUnit::factory()->forMk($mkLama)->forKurikulum($kurikulumLain)->create(['kode' => 'LAMA-01']);
    $kelasLama = KelasMk::query()->create([
        'mk_unit_id' => $mkUnitLama->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $this->dosen->id,
    ]);
    $kmmLama = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasLama->id,
        'mahasiswa_id' => $mahasiswa->id,
        'nilai_angka' => 40,
        'nilai_huruf' => 'E',
    ]);

    $cplAktif = Cpl::factory()->forKurikulum($this->kurikulum)->create(['kode' => 'CPL-AKTIF']);
    $cplLama = Cpl::factory()->forKurikulum($kurikulumLain)->create(['kode' => 'CPL-LAMA']);

    HasilCplMk::query()->create([
        'cpl_id' => $cplAktif->id,
        'mk_unit_id' => $mkUnitAktif->id,
        'kelas_mk_mahasiswa_id' => $kmmAktif->id,
        'semester_id' => $semester->id,
        'nilai_akhir' => 85,
    ]);
    HasilCplMk::query()->create([
        'cpl_id' => $cplLama->id,
        'mk_unit_id' => $mkUnitLama->id,
        'kelas_mk_mahasiswa_id' => $kmmLama->id,
        'semester_id' => $semester->id,
        'nilai_akhir' => 40,
    ]);

    $service = app(IpkKumulatifService::class);
    $baris = collect($service->rosterKurikulum($this->kurikulum))->firstWhere('mahasiswa_id', $mahasiswa->id);
    $capaian = $service->capaianCplMahasiswa($mahasiswa->id, $this->kurikulum);

    expect($baris['sks_dikontrak'])->toBe(2)
        ->and($baris['nilai_angka'])->toBe(80.0)
        ->and($capaian)->toHaveCount(1)
        ->and($capaian[0]['cpl_kode'])->toBe('CPL-AKTIF')
        ->and($capaian[0]['nilai_rata_rata'])->toBe(85.0);
});

it('capaianCplMahasiswa merangkum rerata nilai_akhir lintas MK pada kurikulum yang dikerjakan', function () {
    $this->seed(SemesterSeeder::class);

    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id]);

    $mk = Mk::factory()->forKurikulum($this->kurikulum)->create();
    $mkUnit = MkUnit::factory()->forMk($mk)->forKurikulum($this->kurikulum)->create(['kode' => 'KU21518001']);
    $kelas = KelasMk::query()->create(['mk_unit_id' => $mkUnit->id, 'semester_id' => $semester->id, 'kode_kelas' => 'A', 'dosen_pengampu_id' => $this->dosen->id]);
    $kmm = KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelas->id, 'mahasiswa_id' => $mahasiswa->id]);

    $cpl = Cpl::factory()->forKurikulum($this->kurikulum)->create(['kode' => 'CPL05']);

    HasilCplMk::query()->create([
        'cpl_id' => $cpl->id,
        'mk_unit_id' => $mkUnit->id,
        'kelas_mk_mahasiswa_id' => $kmm->id,
        'semester_id' => $semester->id,
        'nilai_akhir' => 80,
    ]);

    $capaian = app(IpkKumulatifService::class)->capaianCplMahasiswa($mahasiswa->id, $this->kurikulum);

    expect($capaian)->toHaveCount(1)
        ->and($capaian[0]['cpl_kode'])->toBe('CPL05')
        ->and($capaian[0]['nilai_rata_rata'])->toBe(80.0);
});
