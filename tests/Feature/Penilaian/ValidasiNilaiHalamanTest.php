<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Penilaian\Filament\Pages\ValidasiNilai;
use App\Modules\Penilaian\Models\ValidasiNilaiKelas;
use App\Modules\Penilaian\Services\PenilaianMatrixService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once __DIR__.'/../../Support/KelasValidasiFixture.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]);

    $this->prodi = AcademicUnit::where('type', 'study_program')->orderBy('id')->firstOrFail();
    $this->validator = User::factory()->create();
    jadikanValidator($this->validator, $this->prodi);
    $this->dosen = User::factory()->create();
    $this->actingAs($this->validator);
});

it('bukan validator ditolak', function () {
    $this->actingAs(User::where('username', 'dosen')->firstOrFail());

    $this->get(ValidasiNilai::getUrl())->assertForbidden();
});

it('validator dengan peran aktif dosen ditolak', function () {
    $this->dosen->assignRole('Dosen Pengampu');
    $this->dosen->assignRole('Validator Nilai');
    jadikanValidator($this->dosen, $this->prodi);
    $this->actingAs($this->dosen);

    // Tanpa pilihan di switcher, peran aktif bawaan = peran pertama (Dosen
    // Pengampu) sementara relasi roles() belum disaring: hanya pemeriksaan
    // DelegasiMenu yang menolak di sini.
    expect($this->dosen->hasRole('Validator Nilai'))->toBeTrue()
        ->and(ValidasiNilai::canAccess())->toBeFalse();

    ActiveRole::set('Validator Nilai');

    expect(ValidasiNilai::canAccess())->toBeTrue();
});

it('daftar hanya memuat kelas dalam cakupan pada semester terpilih', function () {
    [$prodiA, $prodiB] = siapkanDuaProdi();
    $validatorA = User::factory()->create();
    jadikanValidator($validatorA, $prodiA);
    $kelasA = siapkanKelasValidasi($this->dosen, $prodiA)['kelas'];
    $kelasB = siapkanKelasValidasi(User::factory()->create(), $prodiB)['kelas'];
    $namaA = $kelasA->mkUnit->mk->nama;
    $namaB = $kelasB->mkUnit->mk->nama;
    $this->actingAs($validatorA);

    Livewire::test(ValidasiNilai::class)
        ->assertSee($namaA)
        ->assertDontSee($namaB)
        ->assertSee('Belum divalidasi');
});

it('menampilkan kalimat kosong bila tidak ada kelas dalam cakupan', function () {
    Livewire::test(ValidasiNilai::class)
        ->assertSuccessful()
        ->assertSee('Belum ada kelas dalam cakupan Anda pada semester terpilih.');
});

it('semester lampau bisa dipilih dan kelasnya bisa divalidasi', function () {
    $f = siapkanKelasValidasi($this->dosen, $this->prodi);
    $lampau = Semester::findOrFail($f['kelas']->semester_id);
    Semester::query()->create([
        'kode' => '99991',
        'nama' => 'Ganjil 2099/2100',
        'tahun_mulai' => 2099,
        'tahun_selesai' => 2100,
        'jenis' => 'ganjil',
        'status_aktif' => true,
    ]);
    $namaMk = $f['kelas']->mkUnit->mk->nama;

    $test = Livewire::test(ValidasiNilai::class)
        ->assertDontSee($namaMk)
        ->set('semesterTerpilihId', $lampau->id)
        ->assertSee($namaMk)
        ->call('bukaKelas', $f['kelas']->id)
        ->assertSet('kelasMkId', $f['kelas']->id)
        ->callAction('validasi')
        ->assertNotified('Nilai kelas divalidasi');

    expect(ValidasiNilaiKelas::where('kelas_mk_id', $f['kelas']->id)->exists())->toBeTrue();
});

it('validasi dari tampilan laporan menyimpan baris dengan sidik laporan', function () {
    $kelas = siapkanKelasValidasi($this->dosen, $this->prodi)['kelas'];

    $test = Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $kelas->id)
        ->callAction('validasi')
        ->assertNotified('Nilai kelas divalidasi');

    $baris = ValidasiNilaiKelas::where('kelas_mk_id', $kelas->id)->firstOrFail();
    expect($baris->validator_id)->toBe($this->validator->id)
        ->and($baris->sidik)->toBe($test->get('sidikLaporan'))
        ->and($baris->sidik)->not->toBeNull()
        ->and($baris->gugur_at)->toBeNull()
        ->and($test->get('statusValidasi'))->toBe('tervalidasi');
});

it('menolak validasi bila data berubah sejak laporan dibuka', function () {
    $f = siapkanKelasValidasi($this->dosen, $this->prodi);

    $test = Livewire::test(ValidasiNilai::class)->call('bukaKelas', $f['kelas']->id);

    DB::table('nilai_mahasiswas')
        ->where('kelas_mk_mahasiswa_id', $f['kmm'][0]->id)
        ->update(['nilai' => 10]);

    $test->callAction('validasi')
        ->assertNotified('Data berubah sejak laporan dibuka. Periksa kembali sebelum memvalidasi.');

    expect(ValidasiNilaiKelas::count())->toBe(0);
});

it('aksi validasi tersembunyi untuk kelas sendiri dan menampilkan alasannya', function () {
    $kelas = siapkanKelasValidasi($this->validator, $this->prodi)['kelas'];

    Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $kelas->id)
        ->assertActionHidden('validasi')
        ->assertSee('Anda dosen pengampu kelas ini.');
});

it('bukaKelas di luar cakupan diabaikan', function () {
    [, $prodiB] = siapkanDuaProdi();
    $kelasB = siapkanKelasValidasi($this->dosen, $prodiB)['kelas'];

    Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $kelasB->id)
        ->assertSet('kelasMkId', null);
});

it('batalkan validasi menghapus baris', function () {
    $kelas = siapkanKelasValidasi($this->dosen, $this->prodi)['kelas'];

    Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $kelas->id)
        ->callAction('validasi')
        ->assertSet('statusValidasi', 'tervalidasi')
        ->callAction('batalkanValidasi');

    expect(ValidasiNilaiKelas::count())->toBe(0);
});

it('daftar memakai status tersimpan tanpa menghitung sidik', function () {
    $kelas = siapkanKelasValidasi($this->dosen, $this->prodi)['kelas'];
    Livewire::test(ValidasiNilai::class)->call('bukaKelas', $kelas->id)->callAction('validasi');
    // ubah tanpa event: baris tersimpan tetap, daftar tidak menggugurkan
    DB::table('nilai_mahasiswas')->update(['nilai' => 10]);

    Livewire::test(ValidasiNilai::class)->assertSee('Tervalidasi');

    expect(ValidasiNilaiKelas::firstOrFail()->gugur_at)->toBeNull();
});

it('validator bisa mencetak kelas tervalidasi dari halaman ini', function () {
    $kelas = siapkanKelasValidasi($this->dosen, $this->prodi)['kelas'];

    Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $kelas->id)
        ->callAction('validasi')
        ->assertSeeHtml('onclick="silogyCetakLaporanLengkap()"')
        ->assertSee('Divalidasi oleh');
});

it('mengubah kelasMkId langsung ke kelas di luar cakupan tidak memuat laporan', function () {
    [, $prodiB] = siapkanDuaProdi();
    $kelasB = siapkanKelasValidasi($this->dosen, $prodiB)['kelas'];

    Livewire::test(ValidasiNilai::class)
        ->set('kelasMkId', $kelasB->id)
        ->assertSet('kelasMkId', null)
        ->assertSet('rows', []);
});

it('kelas tervalidasi tetap siap cetak setelah render ulang di halaman validator', function () {
    $kelas = siapkanKelasValidasi($this->dosen, $this->prodi)['kelas'];

    $test = Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $kelas->id)
        ->callAction('validasi');

    expect($test->instance()->laporanSesuaiValidasi())->toBeTrue();

    $test->call('$refresh')
        ->assertSeeHtml('onclick="silogyCetakLaporanLengkap()"')
        ->assertSeeHtml('data-silogy="blok-validasi-nilai"')
        ->assertDontSeeHtml('data-silogy="tanda-air-belum-divalidasi"');
    expect($test->instance()->laporanSesuaiValidasi())->toBeTrue();
});

it('mengganti dosen pengampu lewat query builder menggugurkan validasi saat laporan dimuat', function () {
    $kelas = siapkanKelasValidasi($this->dosen, $this->prodi)['kelas'];
    Livewire::test(ValidasiNilai::class)->call('bukaKelas', $kelas->id)->callAction('validasi');

    DB::table('kelas_mk')->where('id', $kelas->id)->update(['dosen_pengampu_id' => User::factory()->create()->id]);

    Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $kelas->id)
        ->assertSet('statusValidasi', 'perlu_ulang');

    expect(ValidasiNilaiKelas::firstOrFail()->gugur_at)->not->toBeNull();
});

it('mengganti nama mata kuliah lewat query builder menggugurkan validasi saat laporan dimuat', function () {
    $kelas = siapkanKelasValidasi($this->dosen, $this->prodi)['kelas'];
    Livewire::test(ValidasiNilai::class)->call('bukaKelas', $kelas->id)->callAction('validasi');

    DB::table('mk')->where('id', $kelas->mkUnit->mk_id)->update(['nama' => 'Nama MK Baru']);

    Livewire::test(ValidasiNilai::class)
        ->call('bukaKelas', $kelas->id)
        ->assertSet('statusValidasi', 'perlu_ulang');
});

it('satu render dengan kelas terbuka menghitung kelengkapan tiap kelas sekali saja', function () {
    $kelasBuka = siapkanKelasValidasi($this->dosen, $this->prodi)['kelas'];
    $kelasLain = siapkanKelasValidasi($this->dosen, $this->prodi)['kelas'];
    $test = Livewire::test(ValidasiNilai::class)->call('bukaKelas', $kelasBuka->id);

    $panggilan = [];
    $penghitung = new class($panggilan) extends PenilaianMatrixService
    {
        /** @param  array<string, int>  $panggilan */
        public function __construct(private array &$panggilan) {}

        public function kelengkapanKelas(KelasMk $kelasMk): array
        {
            $this->panggilan[$kelasMk->id] = ($this->panggilan[$kelasMk->id] ?? 0) + 1;

            return parent::kelengkapanKelas($kelasMk);
        }
    };
    app()->instance(PenilaianMatrixService::class, $penghitung);

    $test->call('$refresh')->assertSee('Validasi');

    expect($panggilan[$kelasLain->id] ?? 0)->toBe(1)
        ->and($panggilan[$kelasBuka->id] ?? 0)->toBe(1);
});

it('daftar kelas segar kembali pada request yang sama setelah validasi dan batal', function () {
    $kelas = siapkanKelasValidasi($this->dosen, $this->prodi)['kelas'];
    $statusDiDaftar = function ($test): string {
        $html = $test->html();
        $awal = strpos($html, 'data-silogy="validasi-nilai-daftar"');
        $akhir = strpos($html, '</table>', $awal);

        return substr($html, $awal, $akhir - $awal);
    };

    $test = Livewire::test(ValidasiNilai::class)->call('bukaKelas', $kelas->id);
    expect($statusDiDaftar($test))->toContain('Belum divalidasi')->not->toContain('Tervalidasi');

    $test->callAction('validasi');
    expect($statusDiDaftar($test))->toContain('Tervalidasi')->not->toContain('Belum divalidasi');

    $test->callAction('batalkanValidasi');
    expect($statusDiDaftar($test))->toContain('Belum divalidasi')->not->toContain('Tervalidasi');
});

it('memuat Chart.js sejak halaman pertama, sebelum ada kelas yang dibuka', function () {
    // Skrip yang baru disisipkan lewat morph Livewire tidak dijalankan peramban,
    // jadi grafik jaring laba-laba kosong bila Chart.js baru ikut saat kelas dibuka.
    [$prodiA] = siapkanDuaProdi();
    $validator = User::factory()->create();
    jadikanValidator($validator, $prodiA);
    $this->actingAs($validator);

    Livewire::test(ValidasiNilai::class)
        ->assertSet('kelasMkId', null)
        ->assertSeeHtml('cdn.jsdelivr.net/npm/chart.js');
});
