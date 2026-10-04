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
        {--mode=terisi : terisi (kurikulum sampai nilai sudah ada) atau kosong (diisi pengunjung)}
        {--mk= : jumlah MK pada contoh terisi (1-6), bawaan dari config simulasi.jumlah_mk}
        {--bersama : jadikan contoh terisi salinan bersama hanya-baca (menggantikan yang lama setelah selesai)}';

    protected $description = 'Bangun satu sandbox simulasi utuh (siap diklaim pengunjung)';

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

            $mk = $this->option('mk');

            $hasil = $simulasi->buat(
                lapor: $lapor,
                mode: $mode,
                jumlahMk: $mk === null || $mk === '' ? null : (int) $mk,
                bersama: (bool) $this->option('bersama'),
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
