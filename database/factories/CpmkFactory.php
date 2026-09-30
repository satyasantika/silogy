<?php

namespace Database\Factories;

use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\CpmkSemester;
use App\Modules\MK\Models\Mk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cpmk>
 */
class CpmkFactory extends Factory
{
    protected $model = Cpmk::class;

    public function definition(): array
    {
        return [
            'kode' => 'CPMK-'.fake()->unique()->numerify('##'),
            'deskripsi' => fake()->sentence(),
            'mk_id' => Mk::factory(),
        ];
    }

    public function forMk(Mk $mk): static
    {
        return $this->state(fn (array $attributes) => [
            'mk_id' => $mk->id,
        ]);
    }

    /**
     * Berlakukan CPMK ini pada satu semester. Semester bukan kolom pada baris
     * CPMK, melainkan lampiran — satu baris boleh dipakai di beberapa
     * semester sekaligus, dan tanpa lampiran CPMK tidak muncul di daftar
     * mana pun.
     */
    public function untukSemester(Semester|string $semester): static
    {
        $semesterId = $semester instanceof Semester ? $semester->id : $semester;

        return $this->afterCreating(function (Cpmk $cpmk) use ($semesterId): void {
            CpmkSemester::query()->firstOrCreate([
                'cpmk_id' => $cpmk->id,
                'semester_id' => $semesterId,
            ]);
        });
    }
}
