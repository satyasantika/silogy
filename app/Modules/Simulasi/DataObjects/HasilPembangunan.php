<?php

namespace App\Modules\Simulasi\DataObjects;

use App\Modules\Simulasi\Models\SimulasiJalan;

final class HasilPembangunan
{
    /**
     * @param  array<string, int>  $cacah
     * @param  array<string, int>  $takDikenal
     */
    public function __construct(
        public readonly SimulasiJalan $jalan,
        public readonly array $cacah,
        public readonly float $durasiDetik,
        public readonly array $takDikenal = [],
    ) {}

    public function ringkasSingkat(): string
    {
        $total = array_sum($this->cacah);

        return sprintf(
            '%d baris dibuat dalam %d entitas (%.1f detik).',
            $total,
            count($this->cacah),
            $this->durasiDetik,
        );
    }
}
