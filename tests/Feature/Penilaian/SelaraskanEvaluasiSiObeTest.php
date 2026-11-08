<?php

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Support\SelaraskanEvaluasiSiObe;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Master evaluasi lama (sebelum penyelarasan SI-OBE) — 9 baris, termasuk
 * studi_kasus — seperti yang masih tersimpan di basis data produksi.
 */
function isiEvaluasiLama(): void
{
    foreach ([
        ['uts', 'Ujian Tengah Semester', 'ujian'],
        ['uas', 'Ujian Akhir Semester', 'ujian'],
        ['quiz', 'Quiz', 'tugas'],
        ['tugas', 'Tugas', 'tugas'],
        ['proyek_individu', 'Proyek Individu', 'proyek'],
        ['proyek_kelompok', 'Proyek Kelompok', 'proyek'],
        ['studi_kasus', 'Studi Kasus', 'studi_kasus'],
        ['partisipasi_individu', 'Partisipasi Individu', 'partisipasi'],
        ['partisipasi_kelompok', 'Partisipasi Kelompok', 'partisipasi'],
    ] as [$kode, $nama, $kategori]) {
        Evaluasi::query()->create(['kode' => $kode, 'nama' => $nama, 'kategori' => $kategori]);
    }
}

it('mengganti nama, mengurutkan, dan melebur studi kasus ke hasil pjbl individu tanpa memutus asesmen', function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(SemesterSeeder::class);
    isiEvaluasiLama();

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $asesmenStudiKasus = komponenUntukSemester($mk->id, $semester->id, [
        'evaluasi_id' => Evaluasi::query()->where('kode', 'studi_kasus')->value('id'),
        'kode' => 'Asesmen01',
        'nama' => 'Analisis kasus pembelajaran',
    ]);
    $asesmenKuis = komponenUntukSemester($mk->id, $semester->id, [
        'evaluasi_id' => Evaluasi::query()->where('kode', 'quiz')->value('id'),
        'kode' => 'Asesmen02',
        'nama' => 'Kuis 1',
    ]);
    $idKuisSebelum = Evaluasi::query()->where('kode', 'quiz')->value('id');

    SelaraskanEvaluasiSiObe::jalankan();

    $proyekIndividu = Evaluasi::query()->where('kode', 'proyek_individu')->firstOrFail();

    expect(Evaluasi::query()->where('kode', 'studi_kasus')->exists())->toBeFalse()
        ->and($asesmenStudiKasus->fresh()->evaluasi_id)->toBe($proyekIndividu->id)
        ->and($asesmenKuis->fresh()->evaluasi_id)->toBe($idKuisSebelum)
        ->and($proyekIndividu->nama)->toBe('Hasil PjBL/PBL/IBL/CBL (Individu)')
        ->and(Evaluasi::query()->urut()->pluck('nama')->all())->toBe([
            'Aktivitas Partisipatif (Individu)',
            'Aktivitas Partisipatif (Kelompok)',
            'Hasil PjBL/PBL/IBL/CBL (Individu)',
            'Hasil PjBL/PBL/IBL/CBL (Kelompok)',
            'Tugas',
            'Kuis',
            'UTS',
            'UAS',
        ]);
});

it('aman dijalankan ulang dan pada basis data yang belum punya master evaluasi', function () {
    SelaraskanEvaluasiSiObe::jalankan();
    expect(Evaluasi::query()->count())->toBe(0);

    isiEvaluasiLama();
    SelaraskanEvaluasiSiObe::jalankan();
    SelaraskanEvaluasiSiObe::jalankan();

    expect(Evaluasi::query()->count())->toBe(8);
});
