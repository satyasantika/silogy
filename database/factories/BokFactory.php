<?php

namespace Database\Factories;

use App\Modules\BoK\Models\Bok;
use App\Modules\Institusi\Models\AcademicUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bok>
 */
class BokFactory extends Factory
{
    protected $model = Bok::class;

    public function definition(): array
    {
        return [
            'kode' => 'BOK-'.fake()->unique()->numerify('##'),
            'nama' => fake()->words(3, true),
            'deskripsi' => fake()->optional()->sentence(),
        ];
    }

    public function forAcademicUnit(AcademicUnit $academicUnit): static
    {
        return $this->state(fn (array $attributes) => [
            'academic_unit_id' => $academicUnit->id,
        ]);
    }
}
