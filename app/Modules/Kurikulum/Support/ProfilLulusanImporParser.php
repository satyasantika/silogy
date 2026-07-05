<?php

namespace App\Modules\Kurikulum\Support;

/**
 * Parser teks indikator profil lulusan berformat penomoran (1), (2), …
 * pada impor massal.
 */
class ProfilLulusanImporParser
{
    /**
     * @return list<string>
     */
    public static function parseIndikators(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [];
        }

        if (! preg_match_all('/\(\d+\)/', $raw, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $markers = $matches[0];
        $items = [];

        foreach ($markers as $index => [$marker, $offset]) {
            $start = $offset + strlen($marker);
            $end = isset($markers[$index + 1]) ? $markers[$index + 1][1] : strlen($raw);
            $teks = trim(substr($raw, $start, $end - $start));

            if ($teks !== '') {
                $items[] = $teks;
            }
        }

        return $items;
    }

    /**
     * @return list<int>
     */
    public static function nomorIndikator(string $raw): array
    {
        if (! preg_match_all('/\((\d+)\)/', $raw, $matches)) {
            return [];
        }

        return array_map(intval(...), $matches[1]);
    }

    public static function jumlahIndikator(string $raw): int
    {
        return count(self::parseIndikators($raw));
    }

    public static function validateIndikator(string $raw): ?string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        $nomor = self::nomorIndikator($raw);

        if ($nomor === []) {
            return 'Indikator harus memakai penomoran (1), (2), (3), … pada awal setiap butir.';
        }

        $items = self::parseIndikators($raw);

        if ($items === []) {
            return 'Setiap nomor (n) harus diikuti teks indikator.';
        }

        if (count($items) !== count($nomor)) {
            return 'Jumlah teks indikator tidak sesuai jumlah nomor yang tertera.';
        }

        $unique = array_values(array_unique($nomor));
        sort($unique);

        if ($unique !== range(1, count($unique))) {
            return 'Penomoran indikator harus berurutan mulai (1) tanpa nomor ganda atau loncat.';
        }

        return null;
    }

    public static function ringkasanIndikator(string $raw): string
    {
        $jumlah = self::jumlahIndikator($raw);

        return $jumlah > 0 ? "{$jumlah} indikator terdeteksi" : 'Tanpa indikator';
    }
}
