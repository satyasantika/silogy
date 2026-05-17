<?php

namespace Database\Factories;

use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subcpmk>
 */
class SubcpmkFactory extends Factory
{
    protected $model = Subcpmk::class;

    public function definition(): array
    {
        return [
            'kode' => 'SUB-'.fake()->unique()->numerify('##'),
            'deskripsi' => fake()->paragraph(),
            'indikator' => fake()->optional()->sentence(),
            'evaluasi' => fake()->optional()->sentence(),
            'bobot' => fake()->optional()->randomFloat(2, 10, 100),
            'bloom_kognitif' => fake()->optional()->randomElement(['C1', 'C2', 'C3', 'C4', 'C5', 'C6']),
            'bloom_afektif' => fake()->optional()->randomElement(['A1', 'A2', 'A3', 'A4', 'A5']),
            'bloom_psikomotorik' => fake()->optional()->randomElement(['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7']),
        ];
    }

    public function forMkCpmk(MkCpmk $mkCpmk): static
    {
        return $this->state(fn (array $attributes) => [
            'mk_cpmk_id' => $mkCpmk->id,
        ]);
    }
}
