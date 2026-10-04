<?php

namespace App\Console\Commands;

use App\Modules\Simulasi\Services\SimulasiService;
use App\Modules\Simulasi\Support\PengaturanSimulasi;
use Illuminate\Console\Command;

class SimulasiStatus extends Command
{
    protected $signature = 'simulasi:status';

    protected $description = 'Tampilkan daftar sandbox simulasi beserta cacah barisnya';

    public function handle(SimulasiService $simulasi): int
    {
        $daftar = $simulasi->daftar();

        $this->components->twoColumnDetail('Mode latihan', $simulasi->cobaPeranTerbuka() ? 'terbuka' : 'tertutup');
        $this->components->twoColumnDetail('Ruang latihan', $simulasi->jumlahRuang().' / '.PengaturanSimulasi::ambil('kapasitas'));

        if ($daftar->isEmpty()) {
            $this->components->info('Belum ada sandbox. Jalankan `simulasi:buat` atau `simulasi:kolam`.');

            return self::SUCCESS;
        }

        $total = $simulasi->totalArtefak();

        $baris = [];

        foreach ($daftar as $jalan) {
            $baris[] = [
                $jalan->kode(),
                $jalan->kosong() ? 'ruang' : ($jalan->bersama ? 'contoh-terisi' : 'terisi'),
                $jalan->pin ?? '—',
                $jalan->status,
                $jalan->terakhir_aktif_pada === null ? '—' : $jalan->terakhir_aktif_pada->diffForHumans(),
                (string) ($total[$jalan->getKey()] ?? 0),
            ];
        }

        $this->table(['Kode', 'Jenis', 'Token', 'Status', 'Aktif terakhir', 'Baris'], $baris);

        return self::SUCCESS;
    }
}
