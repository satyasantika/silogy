<?php

namespace App\Console\Commands;

use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Mahasiswa\Support\AngkatanDariNim;
use Illuminate\Console\Command;

/**
 * Backfill sekali: isi mahasiswas.angkatan dari dua digit pertama NIM
 * untuk baris yang masih NULL/kosong (impor Sintesys lama).
 */
class IsiAngkatanMahasiswaDariNim extends Command
{
    protected $signature = 'mahasiswa:isi-angkatan-dari-nim';

    protected $description = 'Isi kolom angkatan mahasiswa yang kosong dari dua digit pertama NIM';

    public function handle(): int
    {
        $diisi = 0;
        $dilewati = 0;

        Mahasiswa::query()
            ->where(function ($query): void {
                $query->whereNull('angkatan')->orWhere('angkatan', '');
            })
            ->orderBy('id')
            ->each(function (Mahasiswa $mahasiswa) use (&$diisi, &$dilewati): void {
                if (AngkatanDariNim::isiBilaKosong($mahasiswa)) {
                    $diisi++;
                } else {
                    $dilewati++;
                }
            });

        $this->info("Angkatan diisi: {$diisi}. Dilewati (NIM tidak valid / sudah terisi): {$dilewati}.");

        return self::SUCCESS;
    }
}
