<?php

namespace App\Modules\Simulasi\DataObjects;

final class HasilPembongkaran
{
    /**
     * @param  array<string, int>  $dihapus  nama entitas => jumlah terhapus
     * @param  list<array{model: string, id: string, alasan: string}>  $dilewati
     */
    public function __construct(
        public readonly array $dihapus,
        public readonly array $dilewati,
        public readonly bool $diterapkan,
    ) {}

    public function total(): int
    {
        return array_sum($this->dihapus);
    }

    public function ringkasSingkat(): string
    {
        $pesan = sprintf('%d baris dihapus dalam %d entitas.', $this->total(), count($this->dihapus));

        if ($this->dilewati !== []) {
            $pesan .= sprintf(' %d baris dilewati karena masih dirujuk data lain.', count($this->dilewati));
        }

        return $pesan;
    }
}
