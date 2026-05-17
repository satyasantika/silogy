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
            'type' => 'study_program',
            'code' => fake()->unique()->lexify('???'),
            'nama' => fake()->company(),
            'status' => 'aktif',
        ];
    }

    public function university(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'university',
            'parent_id' => null,
        ]);
    }

    public function faculty(AcademicUnit $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'faculty',
            'parent_id' => $parent->id,
        ]);
    }

    public function department(AcademicUnit $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'department',
            'parent_id' => $parent->id,
        ]);
    }

    public function studyProgram(AcademicUnit $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'study_program',
            'parent_id' => $parent->id,
            'jenjang' => 'S1',
        ]);
    }
}
