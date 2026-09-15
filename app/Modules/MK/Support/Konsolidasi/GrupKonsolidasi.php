<?php

namespace App\Modules\MK\Support\Konsolidasi;

/**
 * Satu kelompok baris kembar lintas semester yang akan dilebur menjadi satu
 * baris kanonik. Pemenangnya dipilih deterministik (created_at tertua,
 * tie-break id terkecil) supaya laporan dry-run dan eksekusi selalu sepakat.
 */
final readonly class GrupKonsolidasi
{
    public const ENTITAS_CPMK = 'cpmk';

    public const ENTITAS_SUBCPMK = 'subcpmk';

    public const ENTITAS_KOMPONEN = 'komponen_penilaian';

    /**
     * @param  list<string>  $idPecundang  ID yang akan dihapus setelah FK-nya dialihkan.
     * @param  list<string>  $semesterIds  Semester yang akan dilampirkan ke baris kanonik.
     * @param  list<string>  $bedaKolom  Kolom non-kunci yang isinya berbeda antar anggota.
     */
    public function __construct(
        public string $entitas,
        public string $mkId,
        public string $mkNama,
        public string $kode,
        public string $idKanonik,
        public array $idPecundang,
        public array $semesterIds,
        public array $bedaKolom = [],
        public bool $kembarDalamSemesterSama = false,
    ) {}

    public function jumlahBaris(): int
    {
        return count($this->idPecundang) + 1;
    }

    /**
     * @return list<string>
     */
    public function semuaId(): array
    {
        return [$this->idKanonik, ...$this->idPecundang];
    }
}
