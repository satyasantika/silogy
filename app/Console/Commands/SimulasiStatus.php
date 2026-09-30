<?php

namespace App\Console\Commands;

use App\Modules\Simulasi\Services\SimulasiService;
use Illuminate\Console\Command;

class SimulasiStatus extends Command
{
    protected $signature = 'simulasi:status';

    protected $description = 'Tampilkan keadaan data simulasi beserta cacah artefaknya';

    public function handle(SimulasiService $simulasi): int
    {
        $status = $simulasi->status();

        if (! $status->ada) {
            $this->components->info('Belum ada data simulasi. Jalankan `simulasi:buat` untuk membuatnya.');

            return self::SUCCESS;
        }

        $jalan = $status->jalan;

        $this->components->twoColumnDetail('Status', $jalan->status);
        $this->components->twoColumnDetail('Dimulai', (string) $jalan->mulai_pada);
        $pemicu = $jalan->dipicuOleh;
        $semester = $jalan->semester;

        $this->components->twoColumnDetail('Dipicu oleh', $pemicu === null ? '—' : $pemicu->full_name);
        $this->components->twoColumnDetail('Semester', $semester === null ? '—' : $semester->nama);
        $this->components->twoColumnDetail('Total artefak', (string) $status->totalArtefak());

        $this->newLine();
        $this->table(
            ['Entitas', 'Jumlah'],
            collect($status->cacah)->map(fn (int $n, string $k) => [$k, $n])->values()->all(),
        );

        if ($status->turunan !== []) {
            $this->components->info('Ikut terhapus lewat CASCADE (tidak dicatat satu per satu):');

            foreach ($status->turunan as $label => $jumlah) {
                $this->components->twoColumnDetail($label, (string) $jumlah);
            }
        }

        if ($status->takDikenal !== []) {
            $this->newLine();
            $this->components->warn('Model di luar daftar izin ikut lahir saat pembangunan:');

            foreach ($status->takDikenal as $kelas => $jumlah) {
                $this->components->twoColumnDetail($kelas, (string) $jumlah);
            }
        }

        return self::SUCCESS;
    }
}
