<?php

namespace App\Console\Commands;

use App\Modules\Simulasi\Exceptions\SimulasiSudahAdaException;
use App\Modules\Simulasi\Services\SimulasiService;
use Illuminate\Console\Command;
use Throwable;

class SimulasiBuat extends Command
{
    protected $signature = 'simulasi:buat {--paksa : Bongkar dulu simulasi yang sudah ada, lalu bangun ulang}';

    protected $description = 'Bangun data simulasi end-to-end di dalam pohon unit simulasi';

    public function handle(SimulasiService $simulasi): int
    {
        @set_time_limit(0);

        $lapor = function (string $langkah): void {
            $this->components->task($langkah, fn (): bool => true);
        };

        try {
            $hasil = $this->option('paksa')
                ? $simulasi->bangunUlang(lapor: $lapor)
                : $simulasi->buat(lapor: $lapor);
        } catch (SimulasiSudahAdaException $galat) {
            $this->components->error($galat->getMessage());
            $this->components->info('Pakai `simulasi:buat --paksa` untuk membangun ulang.');

            return self::FAILURE;
        } catch (Throwable $galat) {
            $this->components->error('Pembangunan gagal: '.$galat->getMessage());
            $this->components->info('Artefak yang terlanjur lahir tetap tercatat — jalankan `simulasi:hapus --terapkan` untuk membersihkannya.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info($hasil->ringkasSingkat());

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
