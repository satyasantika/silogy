<?php

namespace App\Modules\MK\Filament\Resources\CpmkResource\Pages;

use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\CpmkSemester;
use App\Modules\MK\Policies\CpmkPolicy;
use App\Modules\MK\Services\CpmkCplPemetaanService;
use App\Modules\MK\Support\MkTerpilih;
use App\Support\Filament\Pages\BaseCreateRecord;
use Illuminate\Auth\Access\AuthorizationException;

class CreateCpmk extends BaseCreateRecord
{
    use HasKoordinatorMkScope;

    protected static string $resource = CpmkResource::class;

    private ?string $kodeCplTerpetakan = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->kodeCplTerpetakan = filled($data['kode_cpl_terpetakan'] ?? null)
            ? (string) $data['kode_cpl_terpetakan']
            : null;
        unset($data['kode_cpl_terpetakan']);

        return $data;
    }

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

    /**
     * CPMK baru langsung berlaku di semester yang sedang dipilih. Semester
     * adalah lampiran, bukan kolom — itulah yang memungkinkan CPMK yang sama
     * dipakai lagi semester depan tanpa membuat ID baru.
     */
    protected function afterCreate(): void
    {
        $semesterId = SemesterTerpilih::currentId(MkTerpilih::currentId()) ?? SemesterTerpilih::defaultId();

        /** @var Cpmk $record */
        $record = $this->getRecord();

        // Sama dengan impor massal: CPL yang dipilih langsung jadi pemetaan
        // mk_cpmk, sehingga CPMK ini muncul di pilihan Sub-CPMK.
        if ($this->kodeCplTerpetakan !== null) {
            CpmkCplPemetaanService::petakanCpmkKeCpl($record, $this->kodeCplTerpetakan);
        }

        if (blank($semesterId)) {
            return;
        }

        CpmkSemester::query()->firstOrCreate([
            'cpmk_id' => $record->id,
            'semester_id' => $semesterId,
        ]);
    }
}
