<?php

namespace App\Console\Commands;

use App\Modules\Simulasi\Exceptions\SimulasiTidakAdaException;
use App\Modules\Simulasi\Services\SimulasiService;
use Illuminate\Console\Command;

/**
 * Mengikuti preseden mk:konsolidasi-lintas-semester: tanpa --terapkan perintah
 * ini hanya melaporkan apa yang akan dihapus.
 */
class SimulasiHapus extends Command
{
    protected $signature = 'simulasi:hapus {--terapkan : Benar-benar hapus; tanpa ini hanya laporan}';

    protected $description = 'Bongkar data simulasi — hanya baris yang tercatat di buku besar';

    public function handle(SimulasiService $simulasi): int
    {
        try {
            $hasil = $simulasi->hapus(terapkan: (bool) $this->option('terapkan'));
        } catch (SimulasiTidakAdaException $galat) {
            $this->components->info($galat->getMessage());

            return self::SUCCESS;
        }

        if (! $hasil->diterapkan) {
            $this->components->warn('LAPORAN SAJA — tidak ada yang dihapus. Tambahkan --terapkan untuk menjalankannya.');
        }

        $this->newLine();
        $this->table(
            ['Entitas', $hasil->diterapkan ? 'Dihapus' : 'Akan dihapus'],
            collect($hasil->dihapus)->map(fn (int $n, string $k) => [$k, $n])->values()->all(),
        );

        $this->newLine();
        $this->components->info(
            'Tidak ikut dihapus: unit akademik nyata, peran, izin, semester, master evaluasi, '
            .'dan setiap baris yang tidak tercatat sebagai buatan simulasi.'
        );

        if ($hasil->dilewati !== []) {
            $this->newLine();
            $this->components->warn(count($hasil->dilewati).' baris dilewati karena masih dirujuk data lain:');

            foreach ($hasil->dilewati as $baris) {
                $this->components->twoColumnDetail($baris['model'].' '.$baris['id'], $baris['alasan']);
            }
        }

        return self::SUCCESS;
    }
}
