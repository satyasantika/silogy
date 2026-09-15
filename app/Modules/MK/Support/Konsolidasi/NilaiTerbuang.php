<?php

namespace App\Modules\MK\Support\Konsolidasi;

/**
 * Nilai mahasiswa yang tidak bisa dipindahkan saat peleburan karena baris
 * tujuan sudah memegang nilai untuk mahasiswa yang sama pada pemetaan yang
 * sama. Selalu dilaporkan utuh — kehilangan nilai tidak boleh senyap.
 */
final readonly class NilaiTerbuang
{
    public function __construct(
        public string $nim,
        public string $mahasiswa,
        public string $subcpmkKode,
        public string $asesmenKode,
        public ?float $nilai,
        public ?float $nilaiDipertahankan,
    ) {}
}
