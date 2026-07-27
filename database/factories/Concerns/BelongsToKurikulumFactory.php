<?php

namespace Database\Factories\Concerns;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use Illuminate\Database\Eloquent\Model;

trait BelongsToKurikulumFactory
{
    public function configure(): static
    {
        return $this->afterMaking(function (Model $model): void {
            if (blank($model->getAttribute('kurikulum_id')) && filled($model->getAttribute('academic_unit_id'))) {
                $model->setAttribute(
                    'kurikulum_id',
                    Kurikulum::query()
                        ->where('academic_unit_id', $model->getAttribute('academic_unit_id'))
                        ->orderByDesc('is_active')
                        ->orderByDesc('tahun')
                        ->value('id'),
                );
            }

            if (blank($model->getAttribute('academic_unit_id')) && filled($model->getAttribute('kurikulum_id'))) {
                $model->setAttribute(
                    'academic_unit_id',
                    Kurikulum::query()
                        ->whereKey($model->getAttribute('kurikulum_id'))
                        ->value('academic_unit_id'),
                );
            }
        });
    }

    public function forKurikulum(Kurikulum $kurikulum): static
    {
        return $this->state(fn (array $attributes): array => [
            'kurikulum_id' => $kurikulum->id,
            'academic_unit_id' => $kurikulum->academic_unit_id,
        ]);
    }

    public function forAcademicUnit(AcademicUnit $academicUnit): static
    {
        return $this->state(function (array $attributes) use ($academicUnit): array {
            $kurikulumId = $attributes['kurikulum_id']
                ?? Kurikulum::query()
                    ->where('academic_unit_id', $academicUnit->id)
                    ->orderByDesc('is_active')
                    ->orderByDesc('tahun')
                    ->value('id');

            return array_filter([
                'academic_unit_id' => $academicUnit->id,
                'kurikulum_id' => $kurikulumId,
            ], fn (mixed $value): bool => $value !== null);
        });
    }
}
