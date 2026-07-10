<?php

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Rules\BobotKomponenSama100Rule;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('menolak bobot jika total komponen bukan 100', function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $mk = Mk::factory()->forAcademicUnit($prodi)->create();
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create();
    $semester = Semester::query()->firstOrCreate(
        ['kode' => '20251'],
        [
            'nama' => 'Ganjil 2025/2026',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2026,
            'jenis' => 'ganjil',
            'status_aktif' => true,
        ],
    );
    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);
    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();

    KomponenPenilaian::query()->create([
        'kelas_mk_id' => $kelas->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 60,
    ]);

    $validator = Validator::make(
        ['bobot' => 30],
        ['bobot' => [new BobotKomponenSama100Rule([$kelas->id], 'UAS')]],
    );

    expect($validator->fails())->toBeTrue();
});

it('menerima bobot jika total komponen tepat 100', function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $mk = Mk::factory()->forAcademicUnit($prodi)->create();
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create();
    $semester = Semester::query()->firstOrCreate(
        ['kode' => '20251'],
        [
            'nama' => 'Ganjil 2025/2026',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2026,
            'jenis' => 'ganjil',
            'status_aktif' => true,
        ],
    );
    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);

    $validator = Validator::make(
        ['bobot' => 100],
        ['bobot' => [new BobotKomponenSama100Rule([$kelas->id], 'UTS')]],
    );

    expect($validator->passes())->toBeTrue();
});

it('tidak menjumlahkan bobot berulang saat kode yang sama ada di beberapa kelas', function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $mk = Mk::factory()->forAcademicUnit($prodi)->create();
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create();
    $semester = Semester::query()->firstOrCreate(
        ['kode' => '20251'],
        [
            'nama' => 'Ganjil 2025/2026',
            'tahun_mulai' => 2025,
            'tahun_selesai' => 2026,
            'jenis' => 'ganjil',
            'status_aktif' => true,
        ],
    );
    $kelasA = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);
    $kelasB = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'B',
    ]);
    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();

    foreach ([$kelasA, $kelasB] as $kelas) {
        KomponenPenilaian::query()->create([
            'kelas_mk_id' => $kelas->id,
            'evaluasi_id' => $evaluasi->id,
            'kode' => 'UTS',
            'nama' => 'UTS',
            'bobot' => 60,
        ]);
    }

    // Total pada mata kuliah harus tetap 60 (bukan 120) walau kode UTS ada di 2 kelas.
    $total = BobotKomponenSama100Rule::totalBobot([$kelasA->id, $kelasB->id], 'UAS', 0);

    expect($total)->toBe(60.0);

    $validator = Validator::make(
        ['bobot' => 40],
        ['bobot' => [new BobotKomponenSama100Rule([$kelasA->id, $kelasB->id], 'UAS')]],
    );

    expect($validator->passes())->toBeTrue();
});
