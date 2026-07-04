<?php

namespace App\Modules\MK\Filament\Resources\SubcpmkResource\Pages;

use App\Modules\MK\Filament\Resources\SubcpmkResource;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Policies\SubcpmkPolicy;
use App\Support\Filament\Pages\BaseCreateRecord;
use Illuminate\Auth\Access\AuthorizationException;

class CreateSubcpmk extends BaseCreateRecord
{
    protected static string $resource = SubcpmkResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();
        $mkCpmkId = $data['mk_cpmk_id'] ?? null;

        if (! $user || ! app(SubcpmkPolicy::class)->create($user)) {
            throw new AuthorizationException;
        }

        $mkCpmk = MkCpmk::query()->with('cpmk')->find($mkCpmkId);
        $mkId = $mkCpmk?->cpmk?->mk_id;

        if ($mkId === null || ! SubcpmkResource::userCanManageMkAsKoordinator($user, $mkId)) {
            throw new AuthorizationException;
        }
    }
}
