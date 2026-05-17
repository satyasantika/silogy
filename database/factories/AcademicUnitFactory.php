<?php

namespace Database\Factories;

use App\Modules\Institusi\Models\AcademicUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicUnit>
 */
class AcademicUnitFactory extends Factory
{
    protected $model = AcademicUnit::class;

    public function definition(): array
    {
        return [
            'type' => 'university',
            'code' => fake()->unique()->lexify('???'),
            'nama' => fake()->company(),
            'status' => 'draft',
        ];
    }

    public function university(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'university',
        ]);
    }

    public function faculty(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'faculty',
        ]);
    }

    public function department(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'department',
        ]);
    }

    public function studyProgram(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'study_program',
            'jenjang' => 'S1',
        ]);
    }
}
