<?php

namespace App\Modules\Kurikulum\States;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\MkUnit;

class SetdosenmkState extends KurikulumState
{
    public static string $name = 'setdosenmk';

    public function transitionTargets(): array
    {
        return [AktifState::class];
    }

    public function canTransition(?string $toStateClass = null): bool
    {
        $unitId = $this->kurikulum()->academic_unit_id;

        $mkUnitIds = MkUnit::query()
            ->where('academic_unit_id', $unitId)
            ->pluck('id');

        if ($mkUnitIds->isEmpty()) {
            return false;
        }

        $kelasQuery = KelasMk::query()->whereIn('mk_unit_id', $mkUnitIds);

        if (! $kelasQuery->exists()) {
            return false;
        }

        return $kelasQuery
            ->whereNull('dosen_pengampu_id')
            ->doesntExist();
    }
}
