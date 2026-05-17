<?php

namespace Database\Seeders;

use App\Modules\Penilaian\Models\Evaluasi;
use Illuminate\Database\Seeder;

class EvaluasiSeeder extends Seeder
{
    /**
     * Master evaluasi default (gen_erd_v5 §10.1).
     *
     * @return array<int, array<string, string|null>>
     */
    public static function evaluasiData(): array
    {
        return [
            [
                'kode' => 'uts',
                'nama' => 'Ujian Tengah Semester',
                'kategori' => 'ujian',
                'workcloud' => 'sumatif',
            ],
            [
                'kode' => 'uas',
                'nama' => 'Ujian Akhir Semester',
                'kategori' => 'ujian',
                'workcloud' => 'sumatif',
            ],
            [
                'kode' => 'quiz',
                'nama' => 'Quiz',
                'kategori' => 'tugas',
                'workcloud' => 'formatif',
            ],
            [
                'kode' => 'tugas',
                'nama' => 'Tugas',
                'kategori' => 'tugas',
                'workcloud' => 'formatif',
            ],
            [
                'kode' => 'proyek_individu',
                'nama' => 'Proyek Individu',
                'kategori' => 'proyek',
                'workcloud' => 'autentik',
            ],
            [
                'kode' => 'proyek_kelompok',
                'nama' => 'Proyek Kelompok',
                'kategori' => 'proyek',
                'workcloud' => 'autentik',
            ],
            [
                'kode' => 'studi_kasus',
                'nama' => 'Studi Kasus',
                'kategori' => 'studi_kasus',
                'workcloud' => 'autentik',
            ],
            [
                'kode' => 'partisipasi_individu',
                'nama' => 'Partisipasi Individu',
                'kategori' => 'partisipasi',
                'workcloud' => 'formatif',
            ],
            [
                'kode' => 'partisipasi_kelompok',
                'nama' => 'Partisipasi Kelompok',
                'kategori' => 'partisipasi',
                'workcloud' => 'formatif',
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::evaluasiData() as $row) {
            Evaluasi::query()->updateOrCreate(
                ['kode' => $row['kode']],
                $row,
            );
        }
    }
}
