<?php

namespace App\Modules\Kelas\Support;

use App\Modules\Kelas\Models\KelasMk;

/**
 * Menyamakan pivot kelas_mk_mahasiswa suatu KelasMk dengan daftar mahasiswa
 * yang berhasil diresolusi dari satu baris payload impor (Sintesys/Simak/JSON
 * massal). Mahasiswa yang tidak lagi disebut pada baris ini (mis. pindah ke
 * kelas lain pada kontrak terbaru) dilepas dari kelas ini — termasuk nilai
 * yang sudah dientri, yang ikut terhapus lewat cascadeOnDelete — karena data
 * kontrak harus selalu mengikuti kondisi terbaru.
 */
final class PesertaKelasRekonsiliasi
{
    /**
     * @param  list<string>  $mahasiswaIds  ID mahasiswa yang berhasil diresolusi/dibuat dari peserta baris ini
     * @return array{terdaftar: int, sudah_terdaftar: int, dihapus: int}
     */
    public static function terapkan(KelasMk $kelas, array $mahasiswaIds): array
    {
        $unikIds = array_values(array_unique(array_filter($mahasiswaIds)));

        $sync = $kelas->mahasiswas()->sync($unikIds);

        return [
            'terdaftar' => count($sync['attached']),
            'sudah_terdaftar' => count($unikIds) - count($sync['attached']),
            'dihapus' => count($sync['detached']),
        ];
    }
}
