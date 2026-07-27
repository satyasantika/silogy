<?php

namespace Database\Factories;

use App\Modules\BoK\Models\Bok;
use Database\Factories\Concerns\BelongsToKurikulumFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bok>
 */
class BokFactory extends Factory
{
    use BelongsToKurikulumFactory;

    protected $model = Bok::class;

    public function definition(): array
    {
        return [
            'kode' => 'BOK-'.fake()->unique()->numerify('##'),
            'nama' => fake()->words(3, true),
            'deskripsi' => fake()->optional()->sentence(),
        ];
    }
}
