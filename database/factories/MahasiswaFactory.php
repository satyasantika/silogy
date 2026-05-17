<?php

namespace Database\Factories;

use App\Modules\Mahasiswa\Models\Mahasiswa;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Mahasiswa>
 */
class MahasiswaFactory extends Factory
{
    protected $model = Mahasiswa::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'nim' => fake()->unique()->numerify('##########'),
            'nama' => fake('id_ID')->name(),
            'jenis_kelamin' => fake()->randomElement(['L', 'P']),
            'angkatan' => (string) fake()->numberBetween(2022, 2025),
            'email' => fake()->unique()->safeEmail(),
            'nomor_wa' => '628'.fake()->numerify('#########'),
            'status' => 'aktif',
        ];
    }
}
