<?php

namespace Database\Seeders;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use Illuminate\Database\Seeder;

class MahasiswaSeeder extends Seeder
{
    public function run(): void
    {
        $prodies = AcademicUnit::query()
            ->where('type', 'study_program')
            ->get();

        foreach ($prodies as $prodi) {
            $existing = Mahasiswa::query()
                ->where('academic_unit_id', $prodi->id)
                ->count();

            if ($existing >= 30) {
                continue;
            }

            Mahasiswa::factory()
                ->count(30 - $existing)
                ->create([
                    'academic_unit_id' => $prodi->id,
                ]);
        }
    }
}
