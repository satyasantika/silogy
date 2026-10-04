<?php

namespace App\Console\Commands;

use App\Modules\Simulasi\Services\SimulasiService;
use Illuminate\Console\Command;

/**
 * Perawatan berkala (penjadwal tiap 5 menit dan langkah akhir deploy):
 * buang ruang yang kedaluwarsa, lalu pastikan contoh terisi bersama ada dan
 * berbentuk terbaru. Ruang latihan TIDAK dibangun di sini: ruang disiapkan
 * Super Admin dari menu Simulasi.
 */
class SimulasiKolam extends Command
{
    protected $signature = 'simulasi:kolam';

    protected $description = 'Buang ruang simulasi kedaluwarsa dan pastikan contoh terisi bersama ada dan terbaru';

    public function handle(SimulasiService $simulasi): int
    {
        if (! config('simulasi.aktif')) {
            return self::SUCCESS;
        }

        $dibuang = $simulasi->bersihkanKedaluwarsa();
        $contoh = $simulasi->rawatContohTerisi();

        $this->components->info("Ruang dibuang: {$dibuang}, contoh terisi: {$contoh}.");

        return self::SUCCESS;
    }
}
