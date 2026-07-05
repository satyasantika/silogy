<?php

namespace App\Modules\MK\Services;

use App\Modules\MK\Filament\Resources\SubcpmkResource;

class SubcpmkKompetensiParser
{
    /**
     * @return array{bloom_kognitif: ?string, bloom_afektif: ?string, bloom_psikomotorik: ?string}
     */
    public static function parse(string $raw): array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return [
                'bloom_kognitif' => null,
                'bloom_afektif' => null,
                'bloom_psikomotorik' => null,
            ];
        }

        $parts = array_map('trim', explode(',', $raw));
        $result = [
            'bloom_kognitif' => null,
            'bloom_afektif' => null,
            'bloom_psikomotorik' => null,
        ];

        $kognitif = SubcpmkResource::bloomKognitifOptions();
        $afektif = SubcpmkResource::bloomAfektifOptions();
        $psikomotorik = SubcpmkResource::bloomPsikomotorikOptions();

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (! preg_match('/^([CAP])(\d+)$/i', $part, $matches)) {
                throw new \InvalidArgumentException(
                    "Format kompetensi tidak valid pada '{$part}'. Gunakan C3,A2,P2 (domain C/A/P + angka).",
                );
            }

            $kode = strtoupper($matches[1].$matches[2]);

            $kolom = match ($matches[1]) {
                'C', 'c' => 'bloom_kognitif',
                'A', 'a' => 'bloom_afektif',
                'P', 'p' => 'bloom_psikomotorik',
                default => throw new \InvalidArgumentException("Domain taksonomi tidak dikenali pada '{$part}'."),
            };

            $opsi = match ($kolom) {
                'bloom_kognitif' => $kognitif,
                'bloom_afektif' => $afektif,
                'bloom_psikomotorik' => $psikomotorik,
            };

            if (! array_key_exists($kode, $opsi)) {
                throw new \InvalidArgumentException("Nilai taksonomi '{$kode}' tidak valid untuk domain ".strtoupper($matches[1]).'.');
            }

            $result[$kolom] = $kode;
        }

        return $result;
    }

    /**
     * @return array{valid: bool, keterangan: string, bloom?: array<string, ?string>}
     */
    public static function validasi(string $raw): array
    {
        try {
            return [
                'valid' => true,
                'keterangan' => '',
                'bloom' => self::parse($raw),
            ];
        } catch (\InvalidArgumentException $exception) {
            return [
                'valid' => false,
                'keterangan' => $exception->getMessage(),
            ];
        }
    }
}
