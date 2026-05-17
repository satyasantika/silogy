<?php

namespace Database\Factories;

use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cpmk>
 */
class CpmkFactory extends Factory
{
    protected $model = Cpmk::class;

    public function definition(): array
    {
        return [
            'kode' => 'CPMK-'.fake()->unique()->numerify('##'),
            'deskripsi' => fake()->sentence(),
            'mk_id' => Mk::factory(),
        ];
    }

    public function forMk(Mk $mk): static
    {
        return $this->state(fn (array $attributes) => [
            'mk_id' => $mk->id,
        ]);
    }
}
