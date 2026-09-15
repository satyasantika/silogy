<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
use App\Modules\Penilaian\Services\NormalisasiBobotKomponenService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum OBE 2025',
        'tahun' => 2025,
        'is_active' => true,
    ]);

    KurikulumTerpilih::set($this->kurikulum->id);

    $this->mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Aplikasi Komputer Matematika',
        'koordinator_mk_id' => $this->korma->id,
    ]);
});

it('menormalisasi bobot walau belum ada kelas MK', function () {
    $evaluasiQuiz = Evaluasi::query()->where('kode', 'quiz')->firstOrFail();
    $evaluasiUts = Evaluasi::query()->where('kode', 'uts')->firstOrFail();

    komponenUntukSemester($this->mk->id, $this->semester->id, [
        'evaluasi_id' => $evaluasiQuiz->id,
        'kode' => 'Asesmen01',
        'nama' => 'Kuis',
        'bobot' => 30,
    ]);
    komponenUntukSemester($this->mk->id, $this->semester->id, [
        'evaluasi_id' => $evaluasiUts->id,
        'kode' => 'Asesmen02',
        'nama' => 'UTS',
        'bobot' => 30,
    ]);

    $hasil = app(NormalisasiBobotKomponenService::class)
        ->normalisasi($this->mk->id, $this->semester->id);

    expect($hasil['status'])->toBe('dinormalisasi')
        ->and($hasil['jumlah_asesmen'])->toBe(2)
        ->and($hasil['total_sebelum'])->toBe(60.0);

    // Bobot dibaca dari pivot semester: baris Asesmen sendiri tidak lagi
    // menyimpan bobot karena satu Asesmen bisa dipakai beberapa semester.
    $totalSesudah = (float) KomponenPenilaianSemester::query()
        ->where('semester_id', $this->semester->id)
        ->whereIn(
            'komponen_penilaian_id',
            KomponenPenilaian::query()->select('id')->where('mk_id', $this->mk->id),
        )
        ->sum('bobot');

    expect($totalSesudah)->toBe(100.0);
});

it('mengembalikan status kosong bila belum ada komponen penilaian', function () {
    $hasil = app(NormalisasiBobotKomponenService::class)
        ->normalisasi($this->mk->id, $this->semester->id);

    expect($hasil['status'])->toBe('kosong')
        ->and($hasil['jumlah_asesmen'])->toBe(0);
});
