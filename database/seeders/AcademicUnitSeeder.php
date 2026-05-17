<?php

namespace Database\Seeders;

use App\Modules\Institusi\Models\AcademicUnit;
use Illuminate\Database\Seeder;

class AcademicUnitSeeder extends Seeder
{
    public function run(): void
    {
        $univ = AcademicUnit::firstOrCreate(
            ['code' => 'UNSIL'],
            AcademicUnit::factory()->university()->make([
                'nama' => 'Universitas Siliwangi',
                'status' => 'aktif',
            ])->toArray()
        );

        $fak = AcademicUnit::firstOrCreate(
            ['code' => 'FT', 'parent_id' => $univ->id],
            AcademicUnit::factory()->faculty($univ)->make([
                'nama' => 'Fakultas Teknik',
                'status' => 'aktif',
            ])->toArray()
        );

        $jur = AcademicUnit::firstOrCreate(
            ['code' => 'INF', 'parent_id' => $fak->id],
            AcademicUnit::factory()->department($fak)->make([
                'nama' => 'Jurusan Informatika',
                'status' => 'aktif',
            ])->toArray()
        );

        AcademicUnit::firstOrCreate(
            ['kode_pddikti' => '55201'],
            AcademicUnit::factory()->studyProgram($jur)->make([
                'nama' => 'S1 Teknik Informatika',
                'code' => 'S1-IF',
                'kode_pddikti' => '55201',
                'jenjang' => 'S1',
                'gelar_lulusan' => 'S.Kom.',
                'status' => 'aktif',
            ])->toArray()
        );
    }
}
