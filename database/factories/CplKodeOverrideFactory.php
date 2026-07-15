<?php

namespace Database\Factories;

use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplKodeOverride;
use App\Modules\Institusi\Models\AcademicUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CplKodeOverride>
 */
class CplKodeOverrideFactory extends Factory
{
    protected $model = CplKodeOverride::class;

    public function definition(): array
    {
        return [
            'kode' => 'CPL-OV-'.fake()->unique()->numerify('##'),
        ];
    }

    public function forAcademicUnit(AcademicUnit $academicUnit): static
    {
        return $this->state(fn (array $attributes) => [
            'academic_unit_id' => $academicUnit->id,
        ]);
    }

    public function forCpl(Cpl $cpl): static
    {
        return $this->state(fn (array $attributes) => [
            'cpl_id' => $cpl->id,
        ]);
    }
}
