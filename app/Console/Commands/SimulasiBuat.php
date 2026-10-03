<?php

namespace App\Console\Commands;

use App\Modules\Simulasi\Exceptions\KapasitasSandboxPenuhException;
use App\Modules\Simulasi\Services\SimulasiService;
use Illuminate\Console\Command;
use Throwable;

class SimulasiBuat extends Command
{
    protected $signature = 'simulasi:buat';

    protected $description = 'Bangun satu sandbox simulasi utuh (siap diklaim pengunjung)';

    public function handle(SimulasiService $simulasi): int
    {
        @set_time_limit(0);

        $lapor = function (string $langkah): void {
            $this->components->task($langkah, fn (): bool => true);
        };

        try {
            $hasil = $simulasi->buat(lapor: $lapor);
        } catch (KapasitasSandboxPenuhException $galat) {
            $this->components->error($galat->getMessage());

            return self::FAILURE;
        } catch (Throwable $galat) {
            $this->components->error('Pembangunan gagal: '.$galat->getMessage());
            $this->components->info('Sandbox setengah jadi sudah dibongkar otomatis.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info($hasil->ringkasSingkat().' Kode sandbox: '.$hasil->jalan->kode());

        $this->table(
            ['Entitas', 'Jumlah'],
            collect($hasil->cacah)->map(fn (int $n, string $k) => [$k, $n])->values()->all(),
        );

        if ($hasil->takDikenal !== []) {
            $this->components->warn('Model di luar daftar izin ikut lahir: '.implode(', ', array_keys($hasil->takDikenal)));
        }

        return self::SUCCESS;
    }
}
