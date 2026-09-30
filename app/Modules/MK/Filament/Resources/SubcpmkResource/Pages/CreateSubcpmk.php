<?php

namespace App\Modules\MK\Filament\Resources\SubcpmkResource\Pages;

use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Filament\Resources\SubcpmkResource;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Models\SubcpmkSemester;
use App\Modules\MK\Policies\SubcpmkPolicy;
use App\Modules\MK\Support\MkTerpilih;
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

    /**
     * Sub-CPMK baru langsung berlaku di semester yang sedang dipilih —
     * semester bukan lagi kolom pada barisnya, melainkan lampiran, supaya
     * baris yang sama bisa dipakai ulang di semester berikutnya.
     */
    protected function afterCreate(): void
    {
        $semesterId = SemesterTerpilih::currentId(MkTerpilih::currentId()) ?? SemesterTerpilih::defaultId();

        if (blank($semesterId)) {
            return;
        }

        /** @var Subcpmk $record */
        $record = $this->getRecord();

        SubcpmkSemester::query()->firstOrCreate([
            'subcpmk_id' => $record->id,
            'semester_id' => $semesterId,
        ]);
    }
}
