<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
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
 * @return array{kelas: KelasMk, kmmDuluan: KelasMkMahasiswa, kmmBelakangan: KelasMkMahasiswa}
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

    $cpmk = Cpmk::factory()->forMk($mk)->create();
    $cpl = Cpl::factory()->forAcademicUnit($prodi)->create(['kode' => 'CPL06']);
    $bok = Bok::factory()->forAcademicUnit($prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $mkCpmk = MkCpmk::factory()->forCplMkAndCpmk($cplMk, $cpmk)->create();
    $subcpmk = Subcpmk::factory()->for($mkCpmk)->create(['kode' => 'SubCPMK04.1']);

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

    return compact('kelas', 'kmmDuluan', 'kmmBelakangan');
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
        ->assertSee('Segera hadir.', escape: false);
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
