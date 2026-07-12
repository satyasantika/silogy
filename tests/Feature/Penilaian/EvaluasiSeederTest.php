<?php

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use Database\Seeders\EvaluasiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('mengisi 9 evaluasi default', function () {
    $this->seed(EvaluasiSeeder::class);

    expect(Evaluasi::query()->count())->toBe(9)
        ->and(Evaluasi::query()->orderBy('kode')->pluck('kode')->all())->toBe([
            'partisipasi_individu',
            'partisipasi_kelompok',
            'proyek_individu',
            'proyek_kelompok',
            'quiz',
            'studi_kasus',
            'tugas',
            'uas',
            'uts',
        ]);
});

it('komponen_penilaian memiliki default bobot 100.00', function () {
    $this->seed(EvaluasiSeeder::class);

    $prodi = AcademicUnit::factory()->create();
    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create();
    $semester = Semester::query()->create([
        'kode' => '20251',
        'nama' => 'Ganjil 2025/2026',
        'tahun_mulai' => 2025,
        'tahun_selesai' => 2026,
        'jenis' => 'ganjil',
        'status_aktif' => true,
    ]);
    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);
    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();

    $komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => $evaluasi->id,
        'nama' => 'UTS Teori',
    ]);

    expect((string) $komponen->fresh()->bobot)->toBe('100.00');

    if (DB::connection()->getDriverName() === 'mysql') {
        $ddl = DB::selectOne('SHOW CREATE TABLE komponen_penilaian')->{'Create Table'} ?? '';

        expect($ddl)->toContain('`bobot` decimal(5,2) NOT NULL DEFAULT 100.00');
    }
});

it('nilai_mahasiswas memakai kolom subcpmk_komponenpenilaian_id', function () {
    expect(Schema::hasColumn('nilai_mahasiswas', 'subcpmk_komponenpenilaian_id'))->toBeTrue()
        ->and(Schema::hasColumn('nilai_mahasiswas', 'subcpmk_komponen_id'))->toBeFalse()
        ->and(Schema::hasColumn('nilai_mahasiswas', 'kelas_mk_mahasiswa_id'))->toBeTrue()
        ->and(Schema::hasColumn('nilai_mahasiswas', 'nilai'))->toBeTrue()
        ->and(Schema::hasColumn('nilai_mahasiswas', 'catatan'))->toBeTrue();
});
