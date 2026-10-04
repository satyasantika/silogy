<?php

namespace App\Console\Commands;

use App\Modules\Simulasi\Exceptions\KapasitasSandboxPenuhException;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use Illuminate\Console\Command;
use Throwable;

class SimulasiBuat extends Command
{
    protected $signature = 'simulasi:buat
        {--mode=kosong : kosong (ruang latihan bertoken) atau terisi (contoh terisi)}
        {--bersama : (diabaikan) contoh terisi selalu salinan bersama hanya-baca}';

    protected $description = 'Bangun satu ruang simulasi (kosong, bertoken) atau contoh terisi bersama';

    public function handle(SimulasiService $simulasi): int
    {
        @set_time_limit(0);

        $lapor = function (string $langkah): void {
            $this->components->task($langkah, fn (): bool => true);
        };

        try {
            $mode = (string) $this->option('mode');

            if (! in_array($mode, SimulasiJalan::MODE, true)) {
                $this->components->error('Mode harus "terisi" atau "kosong".');

                return self::FAILURE;
            }

            $hasil = $simulasi->buat(
                lapor: $lapor,
                mode: $mode,
                // Contoh terisi selalu salinan bersama: tanpa token, ia tak terjangkau siapa pun.
                bersama: $mode === SimulasiJalan::MODE_TERISI || (bool) $this->option('bersama'),
            );
        } catch (KapasitasSandboxPenuhException $galat) {
            $this->components->error($galat->getMessage());

            return self::FAILURE;
        } catch (Throwable $galat) {
            $this->components->error('Pembangunan gagal: '.$galat->getMessage());
            $this->components->info('Sandbox setengah jadi sudah dibongkar otomatis.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info($hasil->ringkasSingkat().' Kode: '.$hasil->jalan->kode().($hasil->jalan->pin ? ', token ruang: '.$hasil->jalan->pin : ''));

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
