<?php

namespace Database\Factories;

use App\Modules\MK\Models\Mk;
use Database\Factories\Concerns\BelongsToKurikulumFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mk>
 */
class MkFactory extends Factory
{
    use BelongsToKurikulumFactory;

    protected $model = Mk::class;

    public function definition(): array
    {
        $sksTeori = fake()->numberBetween(1, 3);
        $sksPraktik = fake()->numberBetween(0, 2);
        $sksLapangan = fake()->numberBetween(0, 1);

        return [
            'state' => 'draft',
            'nama' => fake()->words(4, true),
            'sks_teori' => $sksTeori,
            'sks_praktik' => $sksPraktik,
            'sks_lapangan' => $sksLapangan,
            'sks' => $sksTeori + $sksPraktik + $sksLapangan,
            'jenis' => fake()->randomElement(['wajib', 'pilihan', 'praktikum']),
            'is_active' => true,
        ];
    }
}
