<?php

namespace App\Modules\MK\Support\Konsolidasi;

/**
 * Hasil pembacaan (read-only) atas data yang ada: apa saja yang akan
 * dilebur, semester mana yang akan dilampirkan, dan apa yang berisiko.
 * Dipakai bersama oleh perintah dry-run dan migration yang mengeksekusinya.
 */
final readonly class RencanaKonsolidasi
{
    /**
     * @param  list<GrupKonsolidasi>  $grup
     * @param  list<array{id: string, mk: string, nama: string}>  $komponenTanpaKode
     * @param  list<NilaiTerbuang>  $nilaiTerbuang
     * @param  list<string>  $kelasMkTerdampak
     */
    public function __construct(
        public array $grup,
        public array $komponenTanpaKode,
        public array $nilaiTerbuang,
        public array $kelasMkTerdampak,
    ) {}

    /**
     * @return list<GrupKonsolidasi>
     */
    public function grupUntuk(string $entitas): array
    {
        return array_values(array_filter(
            $this->grup,
            fn (GrupKonsolidasi $grup): bool => $grup->entitas === $entitas,
        ));
    }

    public function kosong(): bool
    {
        return $this->grup === [] && $this->komponenTanpaKode === [];
    }

    public function barisDilebur(): int
    {
        return array_sum(array_map(
            fn (GrupKonsolidasi $grup): int => count($grup->idPecundang),
            $this->grup,
        ));
    }
}
