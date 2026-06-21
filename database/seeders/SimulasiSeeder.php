<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use Database\Seeders\Support\SimulasiAkademikBuilder;
use Illuminate\Database\Seeder;

class SimulasiSeeder extends Seeder
{
    public function run(): void
    {
        $semester = Semester::query()->where('status_aktif', true)->first()
            ?? Semester::query()->where('kode', '20251')->firstOrFail();

        $timkur = User::query()->where('username', 'timkur')->firstOrFail();
        $korma = User::query()->where('username', 'korma')->firstOrFail();
        $dosenProdi = User::query()->where('username', 'dosen')->firstOrFail();
        $dosenUniv = User::query()->where('username', 'dosenuniv')->firstOrFail();
        $dosenFak = User::query()->where('username', 'dosenfak')->firstOrFail();
        $dosenJur = User::query()->where('username', 'dosenjur')->firstOrFail();

        $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
        $fak = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
        $jur = AcademicUnit::query()->where('type', 'department')->firstOrFail();

        $builder = new SimulasiAkademikBuilder(
            semester: $semester,
            timkur: $timkur,
            korma: $korma,
            dosenProdi: $dosenProdi,
        );

        AcademicUnit::query()
            ->where('type', 'study_program')
            ->each(fn (AcademicUnit $prodi) => $builder->seedProdi($prodi));

        $builder->seedMkUnitRingkas([
            'unit' => $univ,
            'kode' => 'UNV101',
            'nama' => 'Pendidikan Pancasila (Simulasi)',
            'dosen' => $dosenUniv,
            'koordinator' => $korma,
        ]);

        $builder->seedMkUnitRingkas([
            'unit' => $fak,
            'kode' => 'FAK101',
            'nama' => 'Metodologi Penelitian Pendidikan (Simulasi)',
            'dosen' => $dosenFak,
            'koordinator' => $korma,
        ]);

        $builder->seedMkUnitRingkas([
            'unit' => $jur,
            'kode' => 'JUR101',
            'nama' => 'Microteaching Matematika (Simulasi)',
            'dosen' => $dosenJur,
            'koordinator' => $korma,
        ]);
    }
}
