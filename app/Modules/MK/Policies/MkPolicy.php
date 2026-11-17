<?php

namespace App\Modules\MK\Policies;

use App\Models\User;
use App\Modules\Kurikulum\Policies\Concerns\AuthorizesTimKurikulumByUnit;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Services\MkUnitResetService;
use Illuminate\Database\Eloquent\Model;

class MkPolicy
{
    use AuthorizesTimKurikulumByUnit;

    protected function kelolaPermission(): string
    {
        return 'kelola_mk';
    }

    /**
     * Hapus MK ikut memusnahkan seluruh penawaran, CPMK, asesmen, dan
     * relasinya. Tim Kurikulum baru boleh setelah semua kontrak kelas dan
     * penilaian pada penawaran MK ini direset; Super Admin langsung.
     */
    public function delete(User $user, Model $model): bool
    {
        if (! $this->manage($user, $model)) {
            return false;
        }

        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return $model instanceof Mk && ! app(MkUnitResetService::class)->adaDataMk($model);
    }

    public function forceDelete(User $user, Model $model): bool
    {
        return $this->delete($user, $model);
    }
}
