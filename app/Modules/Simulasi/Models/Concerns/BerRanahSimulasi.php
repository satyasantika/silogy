<?php

namespace App\Modules\Simulasi\Models\Concerns;

use App\Modules\Simulasi\Support\Ranah;
use App\Modules\Simulasi\Support\RanahSimulasiScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Menaruh sebuah model di bawah pagar ranah simulasi.
 *
 * Model pemakai wajib mendeklarasikan:
 *   public const RANAH_MODE  = 'langsung' | 'unit' | 'mk_unit' | 'pelaku';
 *   public const RANAH_KOLOM = '<kolom penunjuk>';
 *
 * Untuk mode 'langsung', baris baru yang lahir saat ranah sandbox berlaku
 * otomatis diberi sandbox_id — pemanggil (seeder, factory, pengguna sandbox)
 * tidak perlu mengingatnya.
 */
trait BerRanahSimulasi
{
    public static function bootBerRanahSimulasi(): void
    {
        static::addGlobalScope(new RanahSimulasiScope);

        if (static::RANAH_MODE !== 'langsung') {
            return;
        }

        static::creating(function (Model $model): void {
            if (filled($model->getAttribute('sandbox_id'))) {
                return;
            }

            $sandboxId = Ranah::sandboxAktif();

            if ($sandboxId !== null) {
                $model->setAttribute('sandbox_id', $sandboxId);
            }
        });
    }
}
