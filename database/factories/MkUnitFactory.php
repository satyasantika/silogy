<?php

namespace Database\Factories;

use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Factories\Concerns\BelongsToKurikulumFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MkUnit>
 */
class MkUnitFactory extends Factory
{
    use BelongsToKurikulumFactory;

    protected $model = MkUnit::class;

    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->bothify('??####'),
            'semester_ke' => fake()->numberBetween(1, 8),
            'is_active' => true,
        ];
    }

    public function forMk(Mk $mk): static
    {
        return $this->state(fn (array $attributes): array => [
            'mk_id' => $mk->id,
        ]);
    }
}
