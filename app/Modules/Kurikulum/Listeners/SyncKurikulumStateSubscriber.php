<?php

namespace App\Modules\Kurikulum\Listeners;

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Services\KurikulumStateSyncService;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Events\Dispatcher;

/**
 * Sinkronkan state kurikulum setiap data tahap OBE berubah.
 */
class SyncKurikulumStateSubscriber
{
    public function __construct(
        protected KurikulumStateSyncService $syncService,
    ) {}

    /**
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        $saved = 'eloquent.saved: ';
        $deleted = 'eloquent.deleted: ';

        return [
            $saved.ProfilLulusan::class => 'handleProfilLulusan',
            $deleted.ProfilLulusan::class => 'handleProfilLulusan',
            $saved.ProfilIndikator::class => 'handleProfilIndikator',
            $deleted.ProfilIndikator::class => 'handleProfilIndikator',
            $saved.Cpl::class => 'handleCpl',
            $deleted.Cpl::class => 'handleCpl',
            $saved.CplProfilLulusan::class => 'handleCplProfilLulusan',
            $deleted.CplProfilLulusan::class => 'handleCplProfilLulusan',
            $saved.Bok::class => 'handleBok',
            $deleted.Bok::class => 'handleBok',
            $saved.CplBok::class => 'handleCplBok',
            $deleted.CplBok::class => 'handleCplBok',
            $saved.Mk::class => 'handleMk',
            $deleted.Mk::class => 'handleMk',
            $saved.MkUnit::class => 'handleMkUnit',
            $deleted.MkUnit::class => 'handleMkUnit',
            $saved.CplMk::class => 'handleCplMk',
            $deleted.CplMk::class => 'handleCplMk',
            $saved.KelasMk::class => 'handleKelasMk',
            $deleted.KelasMk::class => 'handleKelasMk',
        ];
    }

    public function handleProfilLulusan(ProfilLulusan $profilLulusan): void
    {
        $this->syncService->syncForKurikulum($profilLulusan->kurikulum_id);
    }

    public function handleProfilIndikator(ProfilIndikator $indikator): void
    {
        $indikator->loadMissing('profilLulusan');

        $this->syncService->syncForKurikulum($indikator->profilLulusan?->kurikulum_id);
    }

    public function handleCpl(Cpl $cpl): void
    {
        $this->syncService->syncForUnit($cpl->academic_unit_id);
    }

    public function handleCplProfilLulusan(CplProfilLulusan $pivot): void
    {
        $pivot->loadMissing('profilLulusan', 'cpl');

        $this->syncService->syncForKurikulum($pivot->profilLulusan?->kurikulum_id);
        $this->syncService->syncForUnit($pivot->cpl?->academic_unit_id);
    }

    public function handleBok(Bok $bok): void
    {
        $this->syncService->syncForUnit($bok->academic_unit_id);
    }

    public function handleCplBok(CplBok $cplBok): void
    {
        $cplBok->loadMissing('cpl');

        $this->syncService->syncForUnit($cplBok->cpl?->academic_unit_id);
    }

    public function handleMk(Mk $mk): void
    {
        $this->syncService->syncForUnit($mk->academic_unit_id);
    }

    public function handleMkUnit(MkUnit $mkUnit): void
    {
        $this->syncService->syncForUnit($mkUnit->academic_unit_id);
    }

    public function handleCplMk(CplMk $cplMk): void
    {
        $cplMk->loadMissing('mk');

        $this->syncService->syncForUnit($cplMk->mk?->academic_unit_id);
    }

    public function handleKelasMk(KelasMk $kelasMk): void
    {
        $kelasMk->loadMissing('mkUnit');

        $this->syncService->syncForUnit($kelasMk->mkUnit?->academic_unit_id);
    }
}
