<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use Database\Seeders\Support\SimulasiAkademikBuilder;
use Illuminate\Database\Seeder;

/**
 * Seeder khusus simulasi sistem end-to-end.
 *
 * Fokus pada satu prodi: Pendidikan Matematika dengan FKIP sebagai induk
 * langsung (tanpa jurusan). Seeder ini mandiri — memanggil seeder dasar
 * yang dibutuhkan lalu mengisi seluruh rantai simulasi: kurikulum aktif,
 * profil lulusan, CPL, BoK, MK (prodi + universitas + fakultas), CPMK,
 * Sub-CPMK, kelas, komponen penilaian, nilai mahasiswa, hingga hasil
 * kalkulasi CPL dan agregasi ke unit induk.
 *
 * Jalankan: php artisan db:seed --class=SimulasiSistemSeeder
 */
class SimulasiSistemSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Prasyarat: unit akademik, role+akun, mahasiswa, semester, evaluasi.
        $this->call([
            AcademicUnitSeeder::class,
            RolePermissionSeeder::class,
            MahasiswaSeeder::class,
            SemesterSeeder::class,
            EvaluasiSeeder::class,
        ]);

        $semester = Semester::query()->where('status_aktif', true)->first()
            ?? Semester::query()->orderByDesc('kode')->firstOrFail();

        $timkur = User::query()->where('username', 'timkur')->firstOrFail();
        $korma = User::query()->where('username', 'korma')->firstOrFail();
        $dosenProdi = User::query()->where('username', 'dosen')->firstOrFail();
        $dosenUniv = User::query()->where('username', 'dosenuniv')->firstOrFail();
        $dosenFak = User::query()->where('username', 'dosenfak')->firstOrFail();

        $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
        $fkip = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();

        // 2. Prodi simulasi: Pendidikan Matematika, induk langsung FKIP.
        $prodi = AcademicUnit::query()
            ->where('type', 'study_program')
            ->where('kode_pddikti', '84202')
            ->firstOrFail();

        if ($prodi->parent_id !== $fkip->id) {
            $prodi->update(['parent_id' => $fkip->id]);
        }

        $builder = new SimulasiAkademikBuilder(
            semester: $semester,
            timkur: $timkur,
            korma: $korma,
            dosenProdi: $dosenProdi,
        );

        // 3. Rantai akademik penuh prodi Pendidikan Matematika
        //    (kurikulum→CPL→BoK→MK→CPMK→SubCPMK→kelas→nilai→kalkulasi→agregasi FKIP+Univ).
        $builder->seedProdi($prodi);

        // 4. MK lintas unit: tingkat universitas dan fakultas.
        $builder->seedMkUnitRingkas([
            'unit' => $univ,
            'kode' => 'UNV101',
            'nama' => 'Pendidikan Pancasila (Simulasi)',
            'dosen' => $dosenUniv,
            'koordinator' => $korma,
        ]);

        $builder->seedMkUnitRingkas([
            'unit' => $fkip,
            'kode' => 'FAK101',
            'nama' => 'Metodologi Penelitian Pendidikan (Simulasi)',
            'dosen' => $dosenFak,
            'koordinator' => $korma,
        ]);

        // 5. Adaptasi lintas unit: prodi mengadopsi MK universitas & fakultas
        //    di atas, lalu menyeragamkan kode tampilan CPL/BoK asalnya lewat
        //    kode alias (CplKodeOverride/BokKodeOverride) khusus prodi.
        $builder->seedAdaptasiLintasUnit($prodi, $univ, 'UNV101', 'CPL-ADAPT-UNIV', 'BOK-ADAPT-UNIV');
        $builder->seedAdaptasiLintasUnit($prodi, $fkip, 'FAK101', 'CPL-ADAPT-FAK', 'BOK-ADAPT-FAK');
    }
}
