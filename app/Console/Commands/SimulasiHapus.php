<?php

namespace App\Console\Commands;

use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Services\SimulasiService;
use Illuminate\Console\Command;

/**
 * Tanpa --terapkan perintah ini hanya melaporkan apa yang akan dihapus.
 */
class SimulasiHapus extends Command
{
    protected $signature = 'simulasi:hapus
        {kode? : Kode sandbox (8 karakter); kosong = semua sandbox}
        {--terapkan : Benar-benar hapus; tanpa ini hanya laporan}';

    protected $description = 'Bongkar sandbox simulasi — hanya baris yang tercatat di buku besarnya';

    public function handle(SimulasiService $simulasi): int
    {
        $terapkan = (bool) $this->option('terapkan');

        $sasaran = $simulasi->daftar()
            ->when($this->argument('kode'), fn ($daftar, $kode) => $daftar->filter(fn (SimulasiJalan $j) => $j->kode() === $kode));

        if ($sasaran->isEmpty()) {
            $this->components->info('Tidak ada sandbox yang cocok.');

            return self::SUCCESS;
        }

        if (! $terapkan) {
            $this->components->warn('LAPORAN SAJA — tidak ada yang dihapus. Tambahkan --terapkan untuk menjalankannya.');
        }

        foreach ($sasaran as $jalan) {
            $hasil = $simulasi->hapus($jalan, $terapkan);

            $this->newLine();
            $this->components->twoColumnDetail('Sandbox', $jalan->kode().' ('.$jalan->status.')');
            $this->table(
                ['Entitas', $terapkan ? 'Dihapus' : 'Akan dihapus'],
                collect($hasil->dihapus)->map(fn (int $n, string $k) => [$k, $n])->values()->all(),
            );

            foreach ($hasil->dilewati as $baris) {
                $this->components->twoColumnDetail($baris['model'].' '.$baris['id'], $baris['alasan']);
            }
        }

        $this->newLine();
        $this->components->info(
            'Tidak ikut dihapus: unit akademik nyata, peran, izin, semester, master evaluasi, '
            .'dan setiap baris yang tidak tercatat sebagai buatan simulasi.'
        );

        return self::SUCCESS;
    }
}
