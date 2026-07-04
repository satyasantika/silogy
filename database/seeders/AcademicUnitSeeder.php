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

        // Jurusan bersifat opsional dalam hierarki — dipertahankan sebagai contoh
        // unit level jurusan, tetapi prodi simulasi berinduk langsung ke fakultas.
        AcademicUnit::firstOrCreate(
            ['parent_id' => $fak->id, 'type' => 'department'],
            AcademicUnit::factory()->department($fak)->make([
                'nama' => 'Jurusan Pendidikan Matematika',
                'code' => 'jurmat',
                'status' => 'aktif',
            ])->toArray()
        );

        $prodi = AcademicUnit::firstOrCreate(
            ['kode_pddikti' => '84202'],
            AcademicUnit::factory()->studyProgram($fak)->make([
                'nama' => 'Program Studi S1 Pendidikan Matematika',
                'code' => '2151',
                'kode_pddikti' => '84202',
                'jenjang' => 'S1',
                'gelar_lulusan' => 'S.Pd.',
                'status' => 'aktif',
            ])->toArray()
        );

        if ($prodi->parent_id !== $fak->id) {
            $prodi->update(['parent_id' => $fak->id]);
        }
    }
}
