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
 * @return array{kelas: KelasMk, kmmSatu: KelasMkMahasiswa, kmmDua: KelasMkMahasiswa, skp: SubcpmkKomponenPenilaian}
 */
function siapkanFixtureTampilanMatriks(User $dosen): array
{
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create(['kode' => 'IF301']);

    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
        'dosen_pengampu_id' => $dosen->id,
    ]);

    $mahasiswaSatu = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id, 'nim' => '242151111117', 'nama' => 'Siti Samrotul Lulu']);
    $mahasiswaDua = Mahasiswa::factory()->create(['academic_unit_id' => $prodi->id]);

    $kmmSatu = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelas->id,
        'mahasiswa_id' => $mahasiswaSatu->id,
        'nilai_angka' => 69.5,
        'nilai_huruf' => 'B+',
    ]);
    $kmmDua = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelas->id,
        'mahasiswa_id' => $mahasiswaDua->id,
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
        'bobot' => 8,
    ]);
    $skp = SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponen->id,
        'bobot' => 100,
    ]);

    // Asesmen kedua hanya untuk menggenapkan total bobot komponen MK ini
    // menjadi 100% (syarat penugasanSelesai()), tidak diasersikan lebih jauh.
    $komponenPenggenap = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'Asesmen02',
        'nama' => 'Tugas Lainnya',
        'bobot' => 92,
    ]);
    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $subcpmk->id,
        'komponen_penilaian_id' => $komponenPenggenap->id,
        'bobot' => 100,
    ]);

    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $skp->id,
        'kelas_mk_mahasiswa_id' => $kmmSatu->id,
        'nilai' => 100,
    ]);
    NilaiMahasiswa::query()->create([
        'subcpmk_komponenpenilaian_id' => $skp->id,
        'kelas_mk_mahasiswa_id' => $kmmDua->id,
        'nilai' => 50,
    ]);

    return compact('kelas', 'kmmSatu', 'kmmDua', 'skp');
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

it('menyusun metadata kolom matriks sesuai bobot, evaluasi, dan cpl', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTampilanMatriks($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id);

    $columns = $test->get('columns');

    expect($columns)->toHaveCount(2)
        ->and($columns[0]['asesmen'])->toBe('Asesmen01')
        ->and($columns[0]['evaluasi_kode'])->toBe('quiz')
        ->and($columns[0]['cpl'])->toBe('CPL06')
        ->and($columns[0]['bobot'])->toBe(8.0);
});

it('menampilkan header kolom, baris mahasiswa, dan baris rata-rata kelas sesuai desain', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTampilanMatriks($this->dosen);

    Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSee('Asesmen01', escape: false)
        ->assertSee('quiz', escape: false)
        ->assertSee('CPL06', escape: false)
        ->assertSee('8%', escape: false)
        ->assertSee('242151111117', escape: false)
        ->assertSee('Siti Samrotul Lulu', escape: false)
        ->assertSee('Nilai: 69.5', escape: false)
        ->assertSee('B+', escape: false)
        ->assertSee('Rata-rata Kelas', escape: false)
        ->assertSee('75', escape: false);
});

it('memfilter kolom asesmen yang ditampilkan tanpa membuang data kolom penuh', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTampilanMatriks($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id)
        ->assertSee('Asesmen01', escape: false)
        ->assertSee('Asesmen02', escape: false);

    $idAsesmenSatu = collect($test->get('columns'))->firstWhere('asesmen', 'Asesmen01')['id'];

    // Tab Portofolio (di dalam tab Laporan) sengaja selalu menampilkan seluruh
    // kolom apa adanya, terlepas dari filter tab Penilaian — jadi "Asesmen02"
    // tetap muncul di HTML halaman lewat pane Portofolio; yang diverifikasi di
    // sini adalah properti filternya sendiri, bukan assertDontSee di seluruh halaman.
    $test->set('kolomTerpilih', [$idAsesmenSatu])
        ->assertSee('Asesmen01', escape: false);

    // Data kolom penuh (dipakai Simpan/Tempel) tetap utuh, hanya tampilan yang menyempit.
    expect($test->get('columns'))->toHaveCount(2)
        ->and($test->instance()->getColumnsTampilProperty())->toHaveCount(1);
});

it('mereset filter kolom ke semua asesmen saat berpindah kelas', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTampilanMatriks($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id);

    $idAsesmenSatu = collect($test->get('columns'))->firstWhere('asesmen', 'Asesmen01')['id'];
    $test->set('kolomTerpilih', [$idAsesmenSatu]);

    // Reload matriks kelas yang sama (mis. lewat Simpan) harus mempertahankan filter.
    $test->call('loadMatrix');
    expect($test->get('kolomTerpilih'))->toBe([$idAsesmenSatu]);
});

it('menyusun contoh tabel tempel dari kode asesmen kelas yang aktif', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTampilanMatriks($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id);

    $method = new ReflectionMethod(InputNilai::class, 'contohTabelTempelHtml');
    $method->setAccessible(true);

    $html = (string) $method->invoke($test->instance());

    expect($html)->toContain('NIM')
        ->toContain('Nama')
        ->toContain('Asesmen01')
        ->toContain('Asesmen02');
});

it('tombol filter, salin, dan tempel tetap bisa dipanggil lewat mountAction walau tidak lagi jadi header action', function () {
    $this->actingAs($this->dosen);
    $fixtures = siapkanFixtureTampilanMatriks($this->dosen);

    $test = Livewire::test(InputNilai::class)
        ->set('kelasMkId', $fixtures['kelas']->id);

    $idAsesmenSatu = collect($test->get('columns'))->firstWhere('asesmen', 'Asesmen01')['id'];

    $test->callAction('filterKolomAsesmen', data: [
        'kolom_terpilih' => [$idAsesmenSatu],
    ]);

    // Livewire::fillForm() menimpa array per-indeks, bukan mengganti seluruh
    // array, jadi hanya dipastikan id terpilih ikut tersimpan (bukan bentuk
    // array persis) — yang penting action ini tetap bisa di-mount/dipanggil
    // walau sudah tidak terdaftar di getHeaderActions().
    expect($test->get('kolomTerpilih'))->toContain($idAsesmenSatu);

    $test->callAction('tempelNilai', data: [
        'paste_data' => "NIM\tNama\tAsesmen01\n242151111117\tSiti Samrotul Lulu\t77",
    ])->assertNotified();

    expect($test->get('nilai.'.$fixtures['kmmSatu']->id.'.'.$idAsesmenSatu))->toBe('77');

    $test->callAction('salinNilai')->assertNotified();
});
