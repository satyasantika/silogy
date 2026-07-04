<?php

namespace App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages;

use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kelas\Filament\Resources\KelasMkResource;
use App\Modules\Kelas\Policies\KelasMkPolicy;
use App\Modules\MK\Models\MkUnit;
use App\Support\Filament\Pages\BaseCreateRecord;
use Illuminate\Auth\Access\AuthorizationException;

class CreateKelasMk extends BaseCreateRecord
{
    protected static string $resource = KelasMkResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();
        $mkUnitId = $data['mk_unit_id'] ?? null;

        if ($mkUnitId === null) {
            throw new AuthorizationException;
        }

        $mkUnit = MkUnit::query()->with('academicUnit')->find($mkUnitId);

        if (! $mkUnit?->academicUnit) {
            throw new AuthorizationException;
        }

        $user = auth()->user();

        if (! $user || ! app(KelasMkPolicy::class)->create($user)) {
            throw new AuthorizationException;
        }

        if ($user->hasRole('Super Admin')) {
            return;
        }

        if (! AcademicUnitScope::userHasPivotToUnitOrAncestor($user, $mkUnit->academicUnit)) {
            throw new AuthorizationException;
        }
    }
}
