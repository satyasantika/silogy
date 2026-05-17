<?php

namespace Database\Factories;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MkUnit>
 */
class MkUnitFactory extends Factory
{
    protected $model = MkUnit::class;

    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->bothify('??####'),
            'semester_ke' => fake()->numberBetween(1, 8),
            'is_active' => true,
        ];
    }

    public function forAcademicUnit(AcademicUnit $academicUnit): static
    {
        return $this->state(fn (array $attributes) => [
            'academic_unit_id' => $academicUnit->id,
        ]);
    }

    public function forMk(Mk $mk): static
    {
        return $this->state(fn (array $attributes) => [
            'mk_id' => $mk->id,
        ]);
    }
}
