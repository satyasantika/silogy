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
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Services\EvaluasiCplService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\MahasiswaSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{kelas: KelasMk, kmmDuluan: KelasMkMahasiswa, kmmBelakangan: KelasMkMahasiswa, cpmk: Cpmk, subcpmk: Subcpmk}
 */
function siapkanFixtureTabLaporan(User $dosen): array
{
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create(['kode' => 'IF401']);

    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $dosen->id,
    ]);

    // NIM lebih kecil dibuat belakangan — memastikan urutan baris Portofolio
    // memang mengikuti NIM, bukan urutan pembuatan/nama.
    $mahasiswaDuluan = Mahasiswa::factory()->create([
        'academic_unit_id' => $prodi->id,
        'nim' => '242151111117',
        'nama' => 'Siti Samrotul Lulu',
    ]);
    $mahasiswaBelakangan = Mahasiswa::factory()->create([
        'academic_unit_id' => $prodi->id,
        'nim' => '242151111100',
        'nama' => 'Zaki Abdillah',
    ]);

    $kmmDuluan = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelas->id,
        'mahasiswa_id' => $mahasiswaDuluan->id,
        'nilai_angka' => 69.5,
        'nilai_huruf' => 'B+',
    ]);
    $kmmBelakangan = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelas->id,
        'mahasiswa_id' => $mahasiswaBelakangan->id,
    ]);

    $cpmk = Cpmk::factory()->forMk($mk)->create(['kode' => 'CPMK-1']);
    $cpl = Cpl::factory()->forAcademicUnit($prodi)->create(['kode' => 'CPL06']);
    $bok = Bok::factory()->forAcademicUnit($prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create([
        'kode' => 'SubCPMK04.1',
        'semester_id' => $semester->id,
        'indikator' => 'Mampu menjelaskan konsep dasar',
    ]);

    $evaluasi = Evaluasi::query()->where('kode', 'quiz')->firstOrFail();
    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'Asesmen01',
        'nama' => 'Kuis Konseptual',
        'bobot' => 100,
    ]);
    $skp = SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);

    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $skp->id,
        'kelas_mk_mahasiswa_id' => $kmmDuluan->id,
        'nilai' => 100,
    ]);
    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $skp->id,
        'kelas_mk_mahasiswa_id' => $kmmBelakangan->id,
        'nilai' => 50,
    ]);

    return compact('kelas', 'kmmDuluan', 'kmmBelakangan', 'cpmk', 'subcpmk');
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);
    $this->seed(MahasiswaSeeder::class);

    $this->dosen = User::where('username', 'dosen')->firstOrFail();
});

it('menampilkan struktur tab Penilaian dan Laporan beserta 5 sub-tab laporan', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSee('Penilaian', escape: false)
        ->assertSee('Laporan', escape: false)
        ->assertSee('Portofolio', escape: false)
        ->assertSee('Evaluasi CPL v1', escape: false)
        ->assertSee('Evaluasi CPL v2', escape: false)
        ->assertSee('Hasil Analisis per Mahasiswa', escape: false)
        ->assertSee('Laporan Mata Kuliah ke Prodi', escape: false);
});

it('portofolioRows terurut berdasarkan nim, bukan nama atau urutan dibuat', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id);

    $portofolioRows = $test->get('portofolioRows');

    expect($portofolioRows[0]['nim'])->toBe('242151111100')
        ->and($portofolioRows[1]['nim'])->toBe('242151111117');

    // $rows (tab Penilaian) tetap terurut nama, tidak ikut berubah.
    $rows = $test->get('rows');
    expect($rows[0]['nama'])->toBe('Siti Samrotul Lulu');
});

it('pane Portofolio baca-saja: tidak ada input nilai atau tombol aksi apa pun', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSee('242151111100', escape: false)
        ->assertSee('242151111117', escape: false)
        ->assertSee('Rata-rata Kelas', escape: false);

    $html = $test->html();

    $anchor = 'matriks-portofolio-'.$fixtures['kelas']->id;
    $mulai = strpos($html, $anchor);

    expect($mulai)->not->toBeFalse();

    $akhirTabel = strpos($html, '</table>', $mulai);
    $panePortofolio = substr($html, $mulai, $akhirTabel - $mulai);

    // Tidak ada wire:model nilai atau elemen input di dalam tabel Portofolio
    // (kelas CSS/markup portofolio-* dipakai untuk tabel baca-saja).
    expect($panePortofolio)->not->toContain('wire:model="nilai.')
        ->not->toContain('<input');
});

it('mengelompokkan kolom Portofolio per jenis penilaian dan mengakumulasi nilai komponen', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    // Fixture sudah punya 1 komponen "Asesmen01" (evaluasi quiz, bobot 100).
    // Tambah komponen kedua dengan evaluasi yang SAMA (quiz) supaya kolom
    // Portofolio harus menggabungkan keduanya jadi satu kolom "Quiz" dengan
    // nilai terakumulasi, bukan dua kolom terpisah.
    $komponenSatu = KomponenPenilaian::query()->where('kode', 'Asesmen01')->firstOrFail();
    $komponenSatu->update(['bobot' => 60]);

    $evaluasiQuiz = Evaluasi::query()->where('kode', 'quiz')->firstOrFail();
    $komponenDua = KomponenPenilaian::query()->create([
        'mk_id' => $komponenSatu->mk_id,
        'semester_id' => $komponenSatu->semester_id,
        'evaluasi_id' => $evaluasiQuiz->id,
        'kode' => 'Asesmen02',
        'nama' => 'Kuis Praktik',
        'bobot' => 40,
    ]);
    $skpDua = SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $fixtures['subcpmk']->id,
        'komponen_penilaian_id' => $komponenDua->id,
        'bobot' => 100,
    ]);

    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $skpDua->id,
        'kelas_mk_mahasiswa_id' => $fixtures['kmmDuluan']->id,
        'nilai' => 40,
    ]);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSee('Nilai Akhir', escape: false)
        ->assertSee('Nilai Komponen Evaluasi', escape: false)
        ->assertSee('Quiz', escape: false);

    $kolomEvaluasi = $test->get('kolomEvaluasi');

    expect($kolomEvaluasi)->toHaveCount(1)
        ->and($kolomEvaluasi[0]['label'])->toBe('Quiz')
        ->and($kolomEvaluasi[0]['bobot'])->toBe(100.0);

    $nilaiEvaluasi = $test->instance()->getNilaiEvaluasiProperty();
    $idKolomQuiz = $kolomEvaluasi[0]['id'];

    // kmmDuluan: Asesmen01=100 (bobot 60), Asesmen02=40 (bobot 40)
    // akumulasi = (60*100 + 40*40) / 100 = 76.0
    expect($nilaiEvaluasi[$fixtures['kmmDuluan']->id][$idKolomQuiz])->toBe(76.0);

    // kmmBelakangan: Asesmen01=50 (bobot 60), Asesmen02 belum dinilai (0)
    // akumulasi = (60*50 + 40*0) / 100 = 30.0
    expect($nilaiEvaluasi[$fixtures['kmmBelakangan']->id][$idKolomQuiz])->toBe(30.0);
});

it('menampilkan rata-rata kelas untuk kolom Nilai Akhir pada baris Rata-rata Kelas', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    // Fixture: kmmDuluan nilai_angka=69.5, kmmBelakangan belum dinilai (null)
    // — isi kmmBelakangan supaya rata-ratanya bisa dihitung dari 2 nilai.
    $fixtures['kmmBelakangan']->update(['nilai_angka' => 50.5, 'nilai_huruf' => 'C-']);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id);

    // (69.5 + 50.5) / 2 = 60.0 -> C+
    expect($test->instance()->getRataRataNilaiAkhirProperty())->toBe(60.0)
        ->and($test->instance()->getHurufRataRataKelasProperty())->toBe('C+');

    $html = $test->html();
    $anchor = 'matriks-portofolio-'.$fixtures['kelas']->id;
    $mulai = strpos($html, $anchor);
    $akhirTabel = strpos($html, '</table>', $mulai);
    $panePortofolio = substr($html, $mulai, $akhirTabel - $mulai);

    $panePortofolioRingkas = preg_replace('/\s+/', ' ', $panePortofolio);

    expect($panePortofolioRingkas)->toContain('Rata-rata Kelas')
        ->toContain('> 60 <')
        ->toContain('C+');
});

it('menjalankan kalkulasi CPL sinkron dan menampilkan ketercapaian CPL kelas', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    // Di test, QUEUE_CONNECTION=sync membuat RecalkulasiCplJob (dipicu saat
    // NilaiMahasiswa dibuat) langsung ikut jalan lewat fixture di atas. Untuk
    // membuktikan halaman ini benar-benar menghitung ulang sendiri secara
    // sinkron (bukan hanya kebetulan sudah terisi) — meniru kondisi produksi
    // nyata di mana worker queue-nya mati — hasil lama dihapus dulu di sini.
    HasilCplMk::query()->delete();
    expect(HasilCplMk::query()->count())->toBe(0);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSee('CPL06', escape: false)
        ->assertSee('75', escape: false)
        ->assertSee('Tercapai', escape: false)
        ->assertSee('Quiz', escape: false);

    expect(HasilCplMk::query()->count())->toBeGreaterThan(0);

    $ketercapaian = $test->get('ketercapaianCpl');

    expect($ketercapaian)->toHaveCount(1)
        ->and($ketercapaian[0]['cpl_kode'])->toBe('CPL06')
        ->and($ketercapaian[0]['rata_rata'])->toBe(75.0)
        ->and($ketercapaian[0]['tercapai'])->toBeTrue()
        ->and($ketercapaian[0]['kontribusi'][0]['nama'])->toBe('Quiz')
        ->and($ketercapaian[0]['kontribusi'][0]['bobot'])->toBe(100.0);
});

it('merekap kontribusi Komponen Penilaian Penyumbang Nilai per jenis penugasan, bukan per Sub-CPMK', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    // Fixture sudah punya 1 Sub-CPMK (SubCPMK04.1) yang menyumbang ke "Quiz"
    // lewat Asesmen01 (bobot 100). Tambah Sub-CPMK KEDUA di bawah CPMK yang
    // SAMA, disumbang lewat komponen lain dengan jenis penugasan yang SAMA
    // ("Quiz") — kontribusinya harus digabung jadi SATU baris "Quiz" dengan
    // bobot terjumlah, bukan tampil sebagai dua baris "Quiz" terpisah.
    $komponenSatu = KomponenPenilaian::query()->where('kode', 'Asesmen01')->firstOrFail();
    $komponenSatu->update(['bobot' => 60]);

    $mkCpmk = $fixtures['subcpmk']->mkCpmk;
    $subcpmkDua = Subcpmk::factory()->for($mkCpmk)->create([
        'kode' => 'SubCPMK04.2',
        'semester_id' => $fixtures['kelas']->semester_id,
        'indikator' => 'Mampu menerapkan konsep dasar',
    ]);

    $evaluasiQuiz = Evaluasi::query()->where('kode', 'quiz')->firstOrFail();
    $komponenDua = KomponenPenilaian::query()->create([
        'mk_id' => $komponenSatu->mk_id,
        'semester_id' => $komponenSatu->semester_id,
        'evaluasi_id' => $evaluasiQuiz->id,
        'kode' => 'Asesmen02',
        'nama' => 'Kuis Praktik',
        'bobot' => 40,
    ]);
    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmkDua->id,
        'komponen_penilaian_id' => $komponenDua->id,
        'bobot' => 100,
    ]);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id);

    $ketercapaian = $test->get('ketercapaianCpl');

    // Kontribusi "Quiz" = (60 * 100/100) + (40 * 100/100) = 100, harus jadi
    // satu baris, bukan ["Quiz"=>60, "Quiz"=>40] terpisah.
    expect($ketercapaian[0]['kontribusi'])->toHaveCount(1)
        ->and($ketercapaian[0]['kontribusi'][0]['nama'])->toBe('Quiz')
        ->and($ketercapaian[0]['kontribusi'][0]['bobot'])->toBe(100.0);
});

it('menyusun distribusi nilai huruf sesuai data KelasMkMahasiswa', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSee('Distribusi Nilai', escape: false)
        ->assertSee('Nilai Huruf', escape: false);

    $distribusi = collect($test->get('distribusiNilaiHuruf'))->keyBy('huruf');

    expect($distribusi)->toHaveCount(10)
        ->and($distribusi['B+']['jumlah'])->toBe(1)
        ->and($distribusi['B+']['persentase'])->toBe(100.0)
        ->and($distribusi['A']['jumlah'])->toBe(0);
});

it('menyusun rincian CPL-CPMK-SubCPMK dengan rowspan dan PK x RN yang benar', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSee('Rekapitulasi Ketercapaian Sumbangan CPL', escape: false)
        ->assertSee('CPMK-1', escape: false)
        ->assertSee('SubCPMK04.1', escape: false)
        ->assertSee('Mampu menjelaskan konsep dasar', escape: false)
        ->assertSee('Asesmen01 - Pengetahuan/Kognitif', escape: false);

    $detail = $test->get('detailCplCpmkSubcpmk');

    // Hanya satu kombinasi CPL/CPMK/SubCPMK/asesmen pada fixture ini, jadi
    // seluruh rowspan harus 1 dan baris satu-satunya menjadi baris "awal"
    // di ketiga level sekaligus.
    expect($detail)->toHaveCount(1);

    $baris = $detail[0];

    expect($baris['cpl_kode'])->toBe('CPL06')
        ->and($baris['cpl_rowspan'])->toBe(1)
        ->and($baris['cpl_awal'])->toBeTrue()
        ->and($baris['cpl_rata_rata'])->toBe(75.0)
        ->and($baris['cpl_tercapai'])->toBeTrue()
        ->and($baris['cpmk_kode'])->toBe('CPMK-1')
        ->and($baris['cpmk_rowspan'])->toBe(1)
        ->and($baris['cpmk_rata_rata'])->toBe(75.0)
        ->and($baris['subcpmk_kode'])->toBe('SubCPMK04.1')
        ->and($baris['subcpmk_rowspan'])->toBe(1)
        ->and($baris['subcpmk_rata_rata'])->toBe(75.0)
        ->and($baris['indikator'])->toBe('Mampu menjelaskan konsep dasar')
        ->and($baris['sumber_data'])->toBe('Asesmen01 - Pengetahuan/Kognitif')
        ->and($baris['pk'])->toBe(100.0)
        ->and($baris['rn'])->toBe(75.0)
        ->and($baris['pk_x_rn'])->toBe(75.0);
});

it('menampilkan baris rekapitulasi ketercapaian MK terhadap CPL di penutup tabel Evaluasi CPL v2', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSee('Persentase ketercapaian MK terhadap perkiraan sumbangan ke CPL', escape: false)
        ->assertSee('Target Kelulusan CPL', escape: false)
        ->assertSee('Rata-rata Ketercapaian', escape: false);

    // Fixture hanya punya 1 CPL (CPL06) dengan rata-rata 75.0, dan target
    // fallback (tidak ada Kurikulum aktif eksplisit) juga 75 — jadi rata-rata
    // keseluruhan CPL = 75.0.
    expect($test->instance()->getRataRataKeseluruhanCplProperty())->toBe(75.0);
});

it('menampilkan tabel Hasil Analisis per Mahasiswa dengan tombol Capaian per baris', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSee('Hasil Analisis MK Dosen per Mahasiswa', escape: false)
        ->assertSee('242151111100', escape: false)
        ->assertSee('242151111117', escape: false)
        ->assertSee('Capaian', escape: false);
});

it('tombol Capaian bisa dipanggil lewat mountAction dengan argumen kmmId', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id);

    $test->mountAction('capaianMahasiswa', ['kmmId' => $fixtures['kmmDuluan']->id]);

    expect($test->instance()->getMountedAction())->not->toBeNull();
});

it('modal Capaian menampilkan grafik CPMK, Sub-CPMK, dan Besaran Nilai Penugasan bila mahasiswa sudah dinilai', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id);

    $method = new ReflectionMethod(InputNilai::class, 'capaianMahasiswaHtml');
    $method->setAccessible(true);

    // kmmDuluan sudah punya nilai_angka (69.5) di fixture -> grafik tampil.
    $html = (string) $method->invoke($test->instance(), $fixtures['kmmDuluan']->id);

    expect($html)->toContain('Grafik Ketercapaian CPMK')
        ->toContain('Grafik Ketercapaian Sub-CPMK')
        ->toContain('Grafik Besaran Nilai Penugasan')
        ->toContain('renderRadarSilogy')
        ->toContain('renderBarSilogy')
        ->not->toContain('belum tersedia');
});

it('modal Capaian menampilkan peringatan alih-alih grafik bila mahasiswa belum dinilai', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id);

    $method = new ReflectionMethod(InputNilai::class, 'capaianMahasiswaHtml');
    $method->setAccessible(true);

    // kmmBelakangan belum punya nilai_angka di fixture -> tampilkan peringatan.
    $html = (string) $method->invoke($test->instance(), $fixtures['kmmBelakangan']->id);

    expect($html)->toContain('Grafik belum dapat ditampilkan karena penilaian mata kuliah mahasiswa ini belum tersedia.')
        ->not->toContain('Grafik Ketercapaian CPMK');
});

it('ketercapaian dan rincian per mahasiswa memakai nilai mahasiswa itu sendiri, bukan rata-rata kelas', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    // Picu loadMatrix() dulu supaya kalkulasi sinkron (hasil_subcpmk dkk.) terisi.
    Livewire::test(InputNilai::class)->set('kelasMkId', $fixtures['kelas']->id);

    $service = app(EvaluasiCplService::class);
    $kelasMk = $fixtures['kelas']->fresh('mkUnit');

    $ketercapaianDuluan = $service->ketercapaianCplPerKelas($kelasMk, $fixtures['kmmDuluan']->id);
    $ketercapaianBelakangan = $service->ketercapaianCplPerKelas($kelasMk, $fixtures['kmmBelakangan']->id);

    expect($ketercapaianDuluan[0]['rata_rata'])->toBe(100.0)
        ->and($ketercapaianBelakangan[0]['rata_rata'])->toBe(50.0);

    $detailDuluan = $service->detailCplCpmkSubcpmk($kelasMk, $fixtures['kmmDuluan']->id);
    $detailBelakangan = $service->detailCplCpmkSubcpmk($kelasMk, $fixtures['kmmBelakangan']->id);

    expect($detailDuluan[0]['rn'])->toBe(100.0)
        ->and($detailDuluan[0]['pk_x_rn'])->toBe(100.0)
        ->and($detailBelakangan[0]['rn'])->toBe(50.0)
        ->and($detailBelakangan[0]['pk_x_rn'])->toBe(50.0);
});

it('menampilkan tab Laporan gabungan dengan identitas, rencana evaluasi, workcloud, dan grafik', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSee('A. Identitas Mata Kuliah', escape: false)
        ->assertSee('IF401', escape: false)
        ->assertSee($this->dosen->full_name, escape: false)
        ->assertSee('B. Tabel Nilai Mahasiswa (Workcloud Utama)', escape: false)
        ->assertSee('C1. Evaluasi Ketercapaian CPL', escape: false)
        ->assertSee('C2. Detail Ketercapaian CPL-CPMK-SubCPMK', escape: false)
        ->assertSee('D. Distribusi Nilai', escape: false)
        ->assertSee('E1. Jaring Laba-laba Ketercapaian CPL', escape: false)
        ->assertSee('E4. Jaring Laba-laba Rata-rata Penugasan', escape: false)
        ->assertSee('cdn.jsdelivr.net/npm/chart.js', escape: false);

    $html = $test->html();

    expect($html)->toContain('radar-cpl-'.$fixtures['kelas']->id)
        ->toContain('radar-cpmk-'.$fixtures['kelas']->id)
        ->toContain('radar-subcpmk-'.$fixtures['kelas']->id)
        ->toContain('radar-asesmen-'.$fixtures['kelas']->id);
});

it('radarData menyusun label dan nilai untuk keempat grafik jaring laba-laba', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTabLaporan($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id);

    $radar = $test->instance()->getRadarDataProperty();

    expect($radar['cpl']['labels'])->toBe(['CPL06'])
        ->and($radar['cpl']['data'])->toBe([75.0])
        ->and($radar['cpmk']['labels'])->toBe(['CPMK-1'])
        ->and($radar['cpmk']['data'])->toBe([75.0])
        ->and($radar['subcpmk']['labels'])->toBe(['SubCPMK04.1'])
        ->and($radar['subcpmk']['data'])->toBe([75.0])
        ->and($radar['asesmen']['labels'])->toBe(['Asesmen01'])
        ->and($radar['asesmen']['data'])->toBe([75.0]);
});
