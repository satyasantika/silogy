<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * SimulasiSistemSeeder sudah memanggil seluruh seeder dasar
     * (unit akademik, role+akun, mahasiswa, semester, evaluasi)
     * lalu membangun simulasi lengkap prodi Pendidikan Matematika.
     */
    public function run(): void
    {
        $this->call([
            SimulasiSistemSeeder::class,
        ]);
    }
}
