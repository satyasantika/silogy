<?php

use App\Models\User;
use App\Modules\Penilaian\Models\ValidasiNilaiKelas;
use App\Modules\Penilaian\Services\ValidasiNilaiService;
use App\Modules\Penilaian\Support\StatusValidasiNilai;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\Models\Activity;

require_once __DIR__.'/../../Support/KelasValidasiFixture.php';

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]));

it('validasi → tervalidasi; periksa sidik sama tetap; sidik beda → perlu ulang', function () {
    $kelas = siapkanKelasValidasi(User::factory()->create())['kelas'];
    $validator = User::factory()->create();
    $layanan = app(ValidasiNilaiService::class);

    expect($layanan->status($kelas))->toBe(StatusValidasiNilai::Belum);
    $layanan->validasi($kelas, $validator, str_repeat('a', 64));
    expect($layanan->periksa($kelas, str_repeat('a', 64)))->toBe(StatusValidasiNilai::Tervalidasi);
    expect($layanan->periksa($kelas, str_repeat('b', 64)))->toBe(StatusValidasiNilai::PerluUlang);
    expect($kelas->validasiNilai()->first()->gugur_at)->not->toBeNull();
});

it('periksa dengan sidik null menggugurkan', function () {
    $kelas = siapkanKelasValidasi(User::factory()->create())['kelas'];
    $layanan = app(ValidasiNilaiService::class);
    $layanan->validasi($kelas, User::factory()->create(), str_repeat('a', 64));

    expect($layanan->periksa($kelas, null))->toBe(StatusValidasiNilai::PerluUlang);
    expect($layanan->status($kelas))->toBe(StatusValidasiNilai::PerluUlang);
    expect($kelas->validasiNilai()->first()->gugur_at)->not->toBeNull();
});

it('periksa tanpa baris mengembalikan Belum', function () {
    $kelas = siapkanKelasValidasi(User::factory()->create())['kelas'];

    expect(app(ValidasiNilaiService::class)->periksa($kelas, null))->toBe(StatusValidasiNilai::Belum);
    expect(ValidasiNilaiKelas::query()->count())->toBe(0);
});

it('validasi ulang mengosongkan gugur_at dan tetap satu baris', function () {
    $kelas = siapkanKelasValidasi(User::factory()->create())['kelas'];
    $validator = User::factory()->create();
    $layanan = app(ValidasiNilaiService::class);
    $layanan->validasi($kelas, $validator, str_repeat('a', 64));
    $layanan->gugurkan($kelas);

    $layanan->validasi($kelas, $validator, str_repeat('b', 64));

    expect(ValidasiNilaiKelas::query()->where('kelas_mk_id', $kelas->id)->count())->toBe(1);
    $baris = $kelas->validasiNilai()->first();
    expect($baris->gugur_at)->toBeNull();
    expect($baris->sidik)->toBe(str_repeat('b', 64));
    expect($layanan->status($kelas))->toBe(StatusValidasiNilai::Tervalidasi);
});

it('batalkan menghapus baris dan mencatat activity log', function () {
    $kelas = siapkanKelasValidasi(User::factory()->create())['kelas'];
    $layanan = app(ValidasiNilaiService::class);
    $baris = $layanan->validasi($kelas, User::factory()->create(), str_repeat('a', 64));

    $layanan->batalkan($kelas);

    expect($layanan->status($kelas->fresh()))->toBe(StatusValidasiNilai::Belum);
    expect(ValidasiNilaiKelas::query()->count())->toBe(0);
    expect(Activity::query()
        ->where('subject_type', ValidasiNilaiKelas::class)
        ->where('subject_id', $baris->id)
        ->where('event', 'deleted')
        ->exists())->toBeTrue();
});

it('gugurkan tercatat di activity log', function () {
    $kelas = siapkanKelasValidasi(User::factory()->create())['kelas'];
    $layanan = app(ValidasiNilaiService::class);
    $baris = $layanan->validasi($kelas, User::factory()->create(), str_repeat('a', 64));

    $layanan->gugurkan($kelas);

    $log = Activity::query()
        ->where('subject_type', ValidasiNilaiKelas::class)
        ->where('subject_id', $baris->id)
        ->where('event', 'updated')
        ->latest('id')->first();
    expect($log)->not->toBeNull();
    expect($log->properties['attributes'])->toHaveKey('gugur_at');
});

it('gugurkan tidak mengubah baris yang sudah gugur', function () {
    $kelas = siapkanKelasValidasi(User::factory()->create())['kelas'];
    $layanan = app(ValidasiNilaiService::class);
    $layanan->validasi($kelas, User::factory()->create(), str_repeat('a', 64));
    $layanan->gugurkan($kelas);
    $pertama = $kelas->validasiNilai()->first()->gugur_at;

    $this->travel(5)->minutes();
    $layanan->gugurkan($kelas);

    expect($kelas->validasiNilai()->first()->gugur_at->equalTo($pertama))->toBeTrue();
});

it('dalam mode hanya-baca gugurkan tidak menulis dan periksa tetap mengembalikan PerluUlang', function () {
    $kelas = siapkanKelasValidasi(User::factory()->create())['kelas'];
    $layanan = app(ValidasiNilaiService::class);
    $layanan->validasi($kelas, User::factory()->create(), str_repeat('a', 64));

    $pengguna = User::factory()->create();
    $pengguna->setAttribute('sandbox_id', 'sandbox-uji');
    Cache::put('sim-bersama:sandbox-uji', true, 300);
    $this->actingAs($pengguna);

    $layanan->gugurkan($kelas);
    expect($kelas->validasiNilai()->first()->gugur_at)->toBeNull();

    expect($layanan->periksa($kelas, str_repeat('b', 64)))->toBe(StatusValidasiNilai::PerluUlang);
    expect($kelas->validasiNilai()->first()->gugur_at)->toBeNull();
});

it('StatusValidasiNilai::dari(null) adalah Belum dan label berbahasa Indonesia', function () {
    expect(StatusValidasiNilai::dari(null))->toBe(StatusValidasiNilai::Belum);
    expect(StatusValidasiNilai::Belum->label())->toBe('Belum divalidasi');
    expect(StatusValidasiNilai::Tervalidasi->label())->toBe('Tervalidasi');
    expect(StatusValidasiNilai::PerluUlang->label())->toBe('Perlu validasi ulang');
});
