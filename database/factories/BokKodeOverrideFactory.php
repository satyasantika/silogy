<?php

namespace Database\Factories;

use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Models\BokKodeOverride;
use App\Modules\Institusi\Models\AcademicUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BokKodeOverride>
 */
class BokKodeOverrideFactory extends Factory
{
    protected $model = BokKodeOverride::class;

    public function definition(): array
    {
        return [
            'kode' => 'BOK-OV-'.fake()->unique()->numerify('##'),
        ];
    }

    public function forAcademicUnit(AcademicUnit $academicUnit): static
    {
        return $this->state(fn (array $attributes) => [
            'academic_unit_id' => $academicUnit->id,
        ]);
    }

    public function forBok(Bok $bok): static
    {
        return $this->state(fn (array $attributes) => [
            'bok_id' => $bok->id,
        ]);
    }
}
