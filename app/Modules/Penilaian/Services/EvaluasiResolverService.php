<?php

namespace App\Modules\Penilaian\Services;

use App\Modules\Penilaian\Models\Evaluasi;

class EvaluasiResolverService
{
    /**
     * Cari master evaluasi berdasarkan kode atau nama (case-insensitive).
     */
    public static function cariDariKodeAtauNama(string $input): ?Evaluasi
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        $normal = mb_strtolower($input);

        return Evaluasi::query()
            ->where(function ($query) use ($normal): void {
                $query->whereRaw('LOWER(kode) = ?', [$normal])
                    ->orWhereRaw('LOWER(nama) = ?', [$normal]);
            })
            ->first();
    }

    /**
     * @return array{valid: bool, keterangan: string}
     */
    public static function validasi(string $input): array
    {
        $input = trim($input);

        if ($input === '') {
            return ['valid' => false, 'keterangan' => 'Komponen penilaian (evaluasi) wajib diisi.'];
        }

        if (self::cariDariKodeAtauNama($input) === null) {
            return [
                'valid' => false,
                'keterangan' => "Komponen penilaian '{$input}' tidak ditemukan (gunakan kode atau nama dari master evaluasi).",
            ];
        }

        return ['valid' => true, 'keterangan' => ''];
    }
}
