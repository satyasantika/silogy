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

it('menolak bobot jika total komponen per kelas bukan 100', function () {
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
        'nama' => 'UTS',
        'bobot' => 60,
    ]);

    $validator = Validator::make(
        ['bobot' => 30],
        ['bobot' => [new BobotKomponenSama100Rule($kelas->id)]],
    );

    expect($validator->fails())->toBeTrue();
});

it('menerima bobot jika total komponen per kelas tepat 100', function () {
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

    $validator = Validator::make(
        ['bobot' => 100],
        ['bobot' => [new BobotKomponenSama100Rule($kelas->id)]],
    );

    expect($validator->passes())->toBeTrue();
});
