<?php

namespace App\Modules\Simulasi\DataObjects;

use App\Modules\Simulasi\Models\SimulasiJalan;

final class StatusSimulasi
{
    /**
     * @param  array<string, int>  $cacah  nama entitas => jumlah baris milik simulasi
     * @param  array<string, int>  $turunan  baris yang ikut terhapus lewat CASCADE
     * @param  array<string, int>  $takDikenal
     */
    public function __construct(
        public readonly bool $ada,
        public readonly ?SimulasiJalan $jalan = null,
        public readonly array $cacah = [],
        public readonly array $turunan = [],
        public readonly array $takDikenal = [],
    ) {}

    public function sedangBerjalan(): bool
    {
        return $this->jalan?->sedangBerjalan() ?? false;
    }

    public function gagal(): bool
    {
        return $this->jalan?->status === SimulasiJalan::STATUS_GAGAL;
    }

    public function totalArtefak(): int
    {
        return array_sum($this->cacah);
    }
}
