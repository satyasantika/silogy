<?php

namespace App\Modules\Kurikulum\Services;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\States\KurikulumState;

/**
 * Menyinkronkan state workflow kurikulum berdasarkan prasyarat pengisian data
 * pada setiap tahap OBE — tanpa aksi manual "Lanjutkan State".
 */
class KurikulumStateSyncService
{
    public function sync(Kurikulum $kurikulum): bool
    {
        $kurikulum->loadMissing('academicUnit');

        $changed = false;

        while (true) {
            $transitioned = false;

            foreach ($kurikulum->state->transitionableStateInstances() as $nextState) {
                /** @var class-string<KurikulumState> $nextClass */
                $nextClass = $nextState::class;

                if (! $kurikulum->state->canTransitionTo($nextClass)) {
                    continue;
                }

                $kurikulum->state->transitionTo($nextClass);
                $kurikulum->refresh();
                $changed = true;
                $transitioned = true;

                break;
            }

            if (! $transitioned) {
                break;
            }
        }

        return $changed;
    }

    public function syncForKurikulum(?string $kurikulumId): void
    {
        if (blank($kurikulumId)) {
            return;
        }

        $kurikulum = Kurikulum::query()->find($kurikulumId);

        if ($kurikulum) {
            $this->sync($kurikulum);
        }
    }

    public function syncForUnit(?string $academicUnitId): void
    {
        if (blank($academicUnitId)) {
            return;
        }

        Kurikulum::query()
            ->where('academic_unit_id', $academicUnitId)
            ->each(fn (Kurikulum $kurikulum): bool => $this->sync($kurikulum));
    }
}
