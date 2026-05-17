<?php

namespace Database\Factories;

use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cpl>
 */
class CplFactory extends Factory
{
    protected $model = Cpl::class;

    public function definition(): array
    {
        return [
            'kode' => 'CPL-'.fake()->unique()->numerify('##'),
            'deskripsi' => fake()->sentence(),
            'domain' => fake()->randomElement(['kognitif', 'afektif', 'psikomotorik', 'gabungan']),
        ];
    }

    public function forAcademicUnit(AcademicUnit $academicUnit): static
    {
        return $this->state(fn (array $attributes) => [
            'academic_unit_id' => $academicUnit->id,
        ]);
    }
}
