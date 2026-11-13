<?php

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Services\ValidasiNilaiService;
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

    $this->dosen = User::where('username', 'dosen')->firstOrFail();
    $this->actingAs($this->dosen);
    $this->fixture = siapkanKelasValidasi($this->dosen);
    $this->kelas = $this->fixture['kelas'];
});

function bukaLaporan(KelasMk $kelas)
{
    return Livewire::test(InputNilai::class)->set('kelasMkId', $kelas->id);
}

function validasiSekarang(KelasMk $kelas, User $dosen): void
{
    $sidik = bukaLaporan($kelas)->get('sidikLaporan');
    expect($sidik)->not->toBeNull();

    app(ValidasiNilaiService::class)->validasi($kelas, User::factory()->create(), $sidik);
}

it('memuat ulang tanpa perubahan tidak menggugurkan', function () {
    validasiSekarang($this->kelas, $this->dosen);
    $sidikAwal = bukaLaporan($this->kelas)->get('sidikLaporan');

    $test = bukaLaporan($this->kelas)->assertSet('statusValidasi', 'tervalidasi');
    $test->call('loadMatrix')->assertSet('statusValidasi', 'tervalidasi');

    expect($test->get('sidikLaporan'))->toBe($sidikAwal);
    expect($this->kelas->validasiNilai()->first()->gugur_at)->toBeNull();
});

it('mengubah nilai langsung menggugurkan lewat observer', function () {
    validasiSekarang($this->kelas, $this->dosen);

    NilaiMahasiswa::query()->first()->update(['nilai' => 10]);

    expect($this->kelas->validasiNilai()->first()->gugur_at)->not->toBeNull();
});

it('mengubah bobot pivot lewat query builder gugur saat laporan dimuat', function () {
    validasiSekarang($this->kelas, $this->dosen);

    DB::table('subcpmk_komponenpenilaian')->update(['bobot' => 50]);

    bukaLaporan($this->kelas)->assertSet('statusValidasi', 'perlu_ulang');
    expect($this->kelas->validasiNilai()->first()->gugur_at)->not->toBeNull();
});

it('menambah peserta gugur saat laporan dimuat', function () {
    validasiSekarang($this->kelas, $this->dosen);

    KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $this->kelas->id,
        'mahasiswa_id' => Mahasiswa::factory()->create(['academic_unit_id' => $this->kelas->mkUnit->academic_unit_id])->id,
    ]);

    bukaLaporan($this->kelas)->assertSet('statusValidasi', 'perlu_ulang');
});

it('mengubah catatan RTL menggugurkan', function () {
    validasiSekarang($this->kelas, $this->dosen);

    bukaLaporan($this->kelas)
        ->assertSet('statusValidasi', 'tervalidasi')
        ->set('isianRtl.umum', 'Catatan baru untuk perbaikan')
        ->call('simpanCatatanRtl')
        ->assertSet('statusValidasi', 'perlu_ulang');

    expect($this->kelas->validasiNilai()->first()->gugur_at)->not->toBeNull();
});

it('penugasan yang rusak menggugurkan walau laporan tidak dirender', function () {
    validasiSekarang($this->kelas, $this->dosen);

    // Kelas kedua yang siap menjaga akses halaman tetap terbuka.
    siapkanKelasValidasi($this->dosen);

    KomponenPenilaianSemester::query()
        ->where('komponen_penilaian_id', $this->fixture['komponen']->id)
        ->update(['bobot' => 90]);

    bukaLaporan($this->kelas)
        ->assertSet('penugasanBelumSelesai', true)
        ->assertSet('sidikLaporan', null)
        ->assertSet('statusValidasi', 'perlu_ulang');

    expect($this->kelas->validasiNilai()->first()->gugur_at)->not->toBeNull();
});

it('kelas tanpa validasi berstatus belum dan infoValidasi null', function () {
    bukaLaporan($this->kelas)
        ->assertSet('statusValidasi', 'belum')
        ->assertSet('infoValidasi', null);
});

it('kelas tervalidasi mengisi infoValidasi dengan nama validator', function () {
    $validator = User::factory()->create();
    $sidik = bukaLaporan($this->kelas)->get('sidikLaporan');
    app(ValidasiNilaiService::class)->validasi($this->kelas, $validator, $sidik);

    bukaLaporan($this->kelas)
        ->assertSet('statusValidasi', 'tervalidasi')
        ->assertSet('infoValidasi.nama', $validator->namaDenganGelar())
        ->assertSet('infoValidasi.jabatan', null);
});

it('menyimpan catatan RTL tanpa perubahan isi tidak menggugurkan', function () {
    validasiSekarang($this->kelas, $this->dosen);

    bukaLaporan($this->kelas)
        ->call('simpanCatatanRtl')
        ->assertSet('statusValidasi', 'tervalidasi');
});
