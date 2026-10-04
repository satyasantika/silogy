<?php

namespace App\Console\Commands;

use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Proses latar belakang yang dipicu tombol Siapkan Ruang. Satu proses
 * membangun semua ruang yang didaftarkan, berurutan.
 */
class SimulasiSelesaikan extends Command
{
    protected $signature = 'simulasi:selesaikan {id* : ID ruang yang sudah didaftarkan}';

    protected $description = 'Selesaikan pembangunan ruang simulasi yang sudah didaftarkan (proses latar belakang)';

    public function handle(SimulasiService $simulasi): int
    {
        @set_time_limit(0);

        $gagal = 0;

        foreach ((array) $this->argument('id') as $id) {
            $jalan = SimulasiJalan::query()->masihAda()->find($id);

            if ($jalan === null || $jalan->status !== SimulasiJalan::STATUS_BERJALAN) {
                $this->components->error("Ruang {$id} tidak ditemukan atau tidak berstatus berjalan.");
                $gagal++;

                continue;
            }

            try {
                $simulasi->selesaikan($jalan);
            } catch (Throwable $galat) {
                // selesaikan() sudah mencatat kegagalan dan membongkar ruang itu.
                $this->components->error("Pembangunan {$id} gagal: ".$galat->getMessage());
                $gagal++;
            }
        }

        return $gagal === 0 ? self::SUCCESS : self::FAILURE;
    }
}
