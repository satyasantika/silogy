<?php

namespace App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\Concerns\ValidatesBobotKomponenSama100;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Policies\KomponenPenilaianPolicy;
use App\Support\Filament\Pages\BaseCreateRecord;
use Illuminate\Auth\Access\AuthorizationException;

class CreateKomponenPenilaian extends BaseCreateRecord
{
    use HasKoordinatorMkScope;
    use ValidatesBobotKomponenSama100;

    protected static string $resource = KomponenPenilaianResource::class;

    protected function beforeCreate(): void
    {
        $data = $this->form->getState();
        $user = auth()->user();
        $kelasMk = KelasMk::query()->find($data['kelas_mk_id'] ?? null);

        if (! $user || ! app(KomponenPenilaianPolicy::class)->create($user)) {
            throw new AuthorizationException;
        }

        if (! $kelasMk instanceof KelasMk) {
            throw new AuthorizationException;
        }

        $komponen = new KomponenPenilaian(['kelas_mk_id' => $kelasMk->id]);
        $komponen->setRelation('kelasMk', $kelasMk);

        if (! app(KomponenPenilaianPolicy::class)->update($user, $komponen)) {
            throw new AuthorizationException;
        }

        $this->validateBobotKomponenSama100($data);
    }
}
