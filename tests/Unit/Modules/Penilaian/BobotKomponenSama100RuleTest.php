<?php

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Rules\BobotKomponenSama100Rule;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function buatSemesterUjiBobot(): Semester
{
    return Semester::query()->firstOrCreate(
        ['kode' => '20251'],
        [
            'nama' => 'Ganjil 2025/2026',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2026,
            'jenis' => 'ganjil',
            'status_aktif' => true,
        ],
    );
}

it('menolak bobot jika total komponen bukan 100', function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $mk = Mk::factory()->forAcademicUnit($prodi)->create();
    $semester = buatSemesterUjiBobot();
    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();

    KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 60,
    ]);

    $validator = Validator::make(
        ['bobot' => 30],
        ['bobot' => [new BobotKomponenSama100Rule($mk->id, $semester->id, 'UAS')]],
    );

    expect($validator->fails())->toBeTrue();
});

it('menerima bobot jika total komponen tepat 100', function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $mk = Mk::factory()->forAcademicUnit($prodi)->create();
    $semester = buatSemesterUjiBobot();

    $validator = Validator::make(
        ['bobot' => 100],
        ['bobot' => [new BobotKomponenSama100Rule($mk->id, $semester->id, 'UTS')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('menjumlahkan bobot antar kode asesmen berbeda pada mata kuliah dan semester yang sama', function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $mk = Mk::factory()->forAcademicUnit($prodi)->create();
    $semester = buatSemesterUjiBobot();
    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();

    KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 60,
    ]);

    $total = BobotKomponenSama100Rule::totalBobot($mk->id, $semester->id, 'UAS', 0);

    expect($total)->toBe(60.0);

    $validator = Validator::make(
        ['bobot' => 40],
        ['bobot' => [new BobotKomponenSama100Rule($mk->id, $semester->id, 'UAS')]],
    );

    expect($validator->passes())->toBeTrue();
});
