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

    protected $description = 'Bersihkan sandbox kedaluwarsa, isi kolam contoh kosong, dan pastikan contoh terisi bersama ada';

    public function handle(SimulasiService $simulasi): int
    {
        if (! config('simulasi.aktif')) {
            return self::SUCCESS;
        }

        $dibuang = $simulasi->bersihkanKedaluwarsa();
        $dibuat = $simulasi->isiKolam();

        // Contoh terisi bersama harus selalu ada; bila belum, dibangun di sini
        // (di luar permintaan pengunjung).
        $adaTerisi = $simulasi->contohTerisi() !== null;

        $this->components->info("Dibuang: {$dibuang}, dibuat: {$dibuat}, contoh terisi: ".($adaTerisi ? 'ada' : 'belum ada').'.');

        return self::SUCCESS;
    }
}
