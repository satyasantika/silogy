<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Menautkan public/manual → docs/user-manual/aset.
 *
 * Gambar manual tinggal di docs/ supaya berkas .md tetap tampil benar saat
 * dibaca langsung di GitHub. Tapi nginx menangkap setiap permintaan berakhiran
 * .png lebih dulu dan tidak pernah meneruskannya ke PHP, sehingga menyajikan
 * gambar lewat rute Laravel selalu berakhir 404 di peladen produksi.
 *
 * Tautan simbolik menyelesaikan keduanya sekaligus: satu sumber berkas, dan
 * nginx menyajikannya langsung tanpa melewati PHP. Pola yang sama dipakai
 * `php artisan storage:link`.
 *
 * DI PRODUKSI pakai `--salin`. Tumpukan produksi menjalankan nginx di container
 * terpisah yang hanya me-mount volume `public/`; tautan simbolik yang menunjuk
 * ke `/var/www/html/docs/...` akan menggantung di container itu dan seluruh
 * gambar tetap 404. Menyalin menaruh berkasnya di dalam volume yang memang
 * dibaca nginx. Entrypoint produksi sudah memakai flag itu.
 */
class PanduanTautkanAset extends Command
{
    protected $signature = 'panduan:tautkan-aset {--salin : Salin berkas alih-alih membuat tautan simbolik}';

    protected $description = 'Tautkan public/manual ke gambar panduan di docs/user-manual/aset';

    public function handle(): int
    {
        $sumber = base_path('docs/user-manual/aset');
        $tujuan = public_path('manual');

        if (! is_dir($sumber)) {
            $this->components->error("Direktori gambar tidak ditemukan: {$sumber}");

            return self::FAILURE;
        }

        if (is_link($tujuan) || is_file($tujuan)) {
            @unlink($tujuan);
        } elseif (is_dir($tujuan)) {
            $this->hapusDirektori($tujuan);
        }

        if ($this->option('salin')) {
            $this->salin($sumber, $tujuan);
            $this->components->info("Gambar panduan disalin ke {$tujuan}.");

            return self::SUCCESS;
        }

        if (! @symlink($sumber, $tujuan)) {
            $this->components->warn('Tautan simbolik gagal dibuat — menyalin sebagai gantinya.');
            $this->salin($sumber, $tujuan);
        }

        $this->components->info('public/manual → docs/user-manual/aset');

        return self::SUCCESS;
    }

    protected function salin(string $sumber, string $tujuan): void
    {
        @mkdir($tujuan, 0o755, true);

        foreach ((array) glob($sumber.'/*') as $berkas) {
            if (is_file($berkas)) {
                copy($berkas, $tujuan.'/'.basename($berkas));
            }
        }
    }

    protected function hapusDirektori(string $jalur): void
    {
        foreach ((array) glob($jalur.'/*') as $berkas) {
            is_dir($berkas) ? $this->hapusDirektori($berkas) : @unlink($berkas);
        }

        @rmdir($jalur);
    }
}
