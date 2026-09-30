<?php

namespace App\Modules\MK\Support\Konsolidasi;

/**
 * Rencana konsolidasi beserta peta ID kanonik yang dipakai untuk
 * mengeksekusinya. Dihitung sekali, dipakai baik oleh laporan dry-run
 * maupun oleh eksekusi, supaya keduanya tidak mungkin berbeda pendapat.
 */
final readonly class AnalisisKonsolidasi
{
    /**
     * @param  array<string, string>  $cpmkKanonik  id pecundang => id kanonik
     * @param  array<string, string>  $mkCpmkKanonik  id pecundang => id kanonik
     * @param  array<string, string>  $subcpmkKanonik  id pecundang => id kanonik
     * @param  array<string, string>  $komponenKanonik  id pecundang => id kanonik
     * @param  array<string, string>  $kodeEfektif  id komponen_penilaian => kode final
     */
    public function __construct(
        public RencanaKonsolidasi $rencana,
        public array $cpmkKanonik,
        public array $mkCpmkKanonik,
        public array $subcpmkKanonik,
        public array $komponenKanonik,
        public array $kodeEfektif,
    ) {}
}
