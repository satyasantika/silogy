<?php

namespace App\Modules\MK\Filament\Resources\CpmkResource\Pages;

use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Policies\CpmkPolicy;
use App\Support\Filament\Pages\BaseCreateRecord;
use Illuminate\Auth\Access\AuthorizationException;

class CreateCpmk extends BaseCreateRecord
{
    use HasKoordinatorMkScope;

    protected static string $resource = CpmkResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();
        $mkId = $data['mk_id'] ?? null;

        if (! $user || ! app(CpmkPolicy::class)->create($user)) {
            throw new AuthorizationException;
        }

        if ($mkId === null || ! static::userCanManageMkAsKoordinator($user, $mkId)) {
            throw new AuthorizationException;
        }
    }
}
