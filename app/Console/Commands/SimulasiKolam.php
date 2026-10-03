<?php

namespace App\Console\Commands;

use App\Modules\Simulasi\Services\SimulasiService;
use Illuminate\Console\Command;

/**
 * Perawatan berkala: buang sandbox kedaluwarsa lalu isi kolam siap-pakai.
 * Dijadwalkan di routes/console.php.
 */
class SimulasiKolam extends Command
{
    protected $signature = 'simulasi:kolam';

    protected $description = 'Bersihkan sandbox kedaluwarsa dan isi kolam sandbox siap-pakai';

    public function handle(SimulasiService $simulasi): int
    {
        if (! config('simulasi.aktif')) {
            return self::SUCCESS;
        }

        $dibuang = $simulasi->bersihkanKedaluwarsa();
        $dibuat = $simulasi->isiKolam();

        $this->components->info("Dibuang: {$dibuang}, dibuat: {$dibuat}.");

        return self::SUCCESS;
    }
}
