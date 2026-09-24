<?php

namespace App\Modules\Panduan\DataObjects;

final class HalamanPanduan
{
    /**
     * @param  list<array{taraf: int, id: string, teks: string}>  $daftarIsi
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $judul,
        public readonly string $isi,
        public readonly array $daftarIsi = [],
        public readonly int $jumlahGambar = 0,
    ) {}
}
