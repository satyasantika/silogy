<?php

namespace App\Modules\MK\Observers;

use App\Modules\MK\Models\Mk;
use App\Modules\MK\Services\KoordinatorMkRoleSync;

/**
 * Tetapkan/cabut role "Koordinator Mata Kuliah" mengikuti kolom
 * mk.koordinator_mk_id (form edit, select inline, impor massal).
 *
 * Dipasang pada created/updated (bukan saved) supaya getOriginal() masih
 * berisi nilai lama — syncOriginal() Laravel baru jalan setelah saved.
 */
class MkObserver
{
    public function __construct(private readonly KoordinatorMkRoleSync $roleSync) {}

    public function created(Mk $mk): void
    {
        $this->roleSync->sinkronkanSetelahPerubahan($mk->koordinator_mk_id, null);
    }

    public function updated(Mk $mk): void
    {
        if (! $mk->wasChanged('koordinator_mk_id')) {
            return;
        }

        $this->roleSync->sinkronkanSetelahPerubahan(
            $mk->koordinator_mk_id,
            $mk->getOriginal('koordinator_mk_id'),
        );
    }

    public function deleted(Mk $mk): void
    {
        if (filled($mk->koordinator_mk_id)) {
            $this->roleSync->cabutJikaTidakAdaPenugasan($mk->koordinator_mk_id);
        }
    }
}
