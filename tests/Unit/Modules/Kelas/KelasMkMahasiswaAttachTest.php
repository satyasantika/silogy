<?php

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('mendaftarkan mahasiswa ke kelas_mk via attach', function () {
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

    $kelas = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semester->id,
        'kode_kelas' => 'A',
    ]);

    $mahasiswa = Mahasiswa::factory()->create([
        'academic_unit_id' => $prodi->id,
    ]);

    $kelas->mahasiswas()->attach($mahasiswa->id);

    expect($kelas->mahasiswas)->toHaveCount(1)
        ->and(KelasMkMahasiswa::query()->count())->toBe(1)
        ->and($kelas->kelasMkMahasiswas->first()->mahasiswa_id)->toBe($mahasiswa->id);
});
