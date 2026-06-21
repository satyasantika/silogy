<?php

namespace Database\Seeders;

use App\Modules\Institusi\Models\AcademicUnit;
use Illuminate\Database\Seeder;

class AcademicUnitSeeder extends Seeder
{
    public function run(): void
    {
        $univ = AcademicUnit::firstOrCreate(
            AcademicUnit::factory()->university()->make([
                'nama' => 'Universitas Siliwangi',
                'code' => 'UNSIL',
                'kode_pddikti' => '001057',
                'status' => 'aktif',
            ])->toArray()
        );

        $fak = AcademicUnit::firstOrCreate(
            ['parent_id' => $univ->id],
            AcademicUnit::factory()->faculty($univ)->make([
                'nama' => 'Fakultas Keguruan dan Ilmu Pendidikan',
                'code' => '21',
                'status' => 'aktif',
            ])->toArray()
        );

        $jur = AcademicUnit::firstOrCreate(
            ['parent_id' => $fak->id],
            AcademicUnit::factory()->department($fak)->make([
                'nama' => 'Jurusan Pendidikan Matematika',
                'code' => 'jurmat',
                'status' => 'aktif',
            ])->toArray()
        );

        AcademicUnit::firstOrCreate(
            ['kode_pddikti' => '84202', 'parent_id' => $jur->id],
            AcademicUnit::factory()->studyProgram($jur)->make([
                'nama' => 'Program Studi S1 Pendidikan Matematika',
                'code' => '2151',
                'kode_pddikti' => '84202',
                'jenjang' => 'S1',
                'gelar_lulusan' => 'S.Pd.',
                'status' => 'aktif',
            ])->toArray()
        );
    }
}
