<?php

namespace App\Modules\Mahasiswa\Support;

use App\Modules\Mahasiswa\Models\Mahasiswa;

/**
 * Menurunkan tahun angkatan dari NIM/NPM Unsil-style: dua digit pertama
 * adalah tahun masuk abad ke-21 (contoh 232151001 → 2023).
 */
final class AngkatanDariNim
{
    public const LABEL_TANPA_ANGKATAN = 'Tanpa angkatan';

    public static function dari(string $nim): ?string
    {
        $nim = trim($nim);

        if (strlen($nim) < 2) {
            return null;
        }

        $yy = substr($nim, 0, 2);

        if (! ctype_digit($yy)) {
            return null;
        }

        return '20'.$yy;
    }

    /**
     * Isi kolom angkatan dari NIM bila masih kosong. Tidak menimpa nilai
     * yang sudah terisi (manual/impor sebelumnya).
     *
     * @return bool true jika kolom angkatan diubah
     */
    public static function isiBilaKosong(Mahasiswa $mahasiswa): bool
    {
        if (filled($mahasiswa->angkatan)) {
            return false;
        }

        $angkatan = self::dari((string) $mahasiswa->nim);

        if ($angkatan === null) {
            return false;
        }

        $mahasiswa->angkatan = $angkatan;
        $mahasiswa->save();

        return true;
    }

    /**
     * Label untuk pengelompokan laporan: angkatan terisi atau bucket cadangan.
     */
    public static function label(?string $angkatan): string
    {
        return filled($angkatan) ? (string) $angkatan : self::LABEL_TANPA_ANGKATAN;
    }
}
