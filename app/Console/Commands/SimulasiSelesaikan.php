<?php

namespace App\Console\Commands;

use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Mengisi sandbox yang sudah didaftarkan dari menu Simulasi. Dipanggil
 * SimulasiService::luncurkan() sebagai proses latar belakang; tidak
 * dimaksudkan untuk dijalankan manual.
 */
class SimulasiSelesaikan extends Command
{
    protected $signature = 'simulasi:selesaikan {id : ID sandbox yang sudah didaftarkan}';

    protected $description = 'Selesaikan pembangunan satu sandbox yang sudah didaftarkan (proses latar belakang)';

    public function handle(SimulasiService $simulasi): int
    {
        @set_time_limit(0);

        $jalan = SimulasiJalan::query()->masihAda()->find($this->argument('id'));

        if ($jalan === null || $jalan->status !== SimulasiJalan::STATUS_BERJALAN) {
            $this->components->error('Sandbox tidak ditemukan atau tidak berstatus berjalan.');

            return self::FAILURE;
        }

        try {
            $simulasi->selesaikan($jalan);
        } catch (Throwable $galat) {
            // selesaikan() sudah mencatat kegagalan dan membongkar sandbox.
            $this->components->error('Pembangunan gagal: '.$galat->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
