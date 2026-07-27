<?php

namespace Database\Factories;

use App\Modules\CPL\Models\Cpl;
use Database\Factories\Concerns\BelongsToKurikulumFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cpl>
 */
class CplFactory extends Factory
{
    use BelongsToKurikulumFactory;

    protected $model = Cpl::class;

    public function definition(): array
    {
        return [
            'kode' => 'CPL-'.fake()->unique()->numerify('##'),
            'deskripsi' => fake()->sentence(),
            'domain' => fake()->randomElements(
                ['kognitif', 'afektif', 'psikomotorik'],
                fake()->numberBetween(1, 3),
            ),
        ];
    }
}
