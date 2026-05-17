<?php

namespace Database\Factories;

use App\Modules\CPL\Models\CplMk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\MkCpmk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MkCpmk>
 */
class MkCpmkFactory extends Factory
{
    protected $model = MkCpmk::class;

    public function definition(): array
    {
        return [
            'bobot' => fake()->randomFloat(2, 10, 100),
        ];
    }

    public function forCplMkAndCpmk(CplMk $cplMk, Cpmk $cpmk): static
    {
        return $this->state(fn (array $attributes) => [
            'cpl_mk_id' => $cplMk->id,
            'cpmk_id' => $cpmk->id,
        ]);
    }
}
