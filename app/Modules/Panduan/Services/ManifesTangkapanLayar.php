<?php

namespace App\Modules\Panduan\Services;

use Illuminate\Support\Facades\File;

/**
 * Pembaca manifes koordinat tangkapan layar yang ditulis
 * scripts/tangkap-layar/jalankan.mjs.
 *
 * Koordinat angka penunjuk dihitung ulang setiap kali layar ditangkap, dari
 * selector elemen yang sebenarnya. Jadi ketika tombol berpindah di antarmuka,
 * penunjuknya ikut berpindah sendiri — tidak ada lagi gambar petunjuk yang
 * diam-diam menunjuk tempat yang salah.
 */
class ManifesTangkapanLayar
{
    /** @var array<string, array{lebar: int, tinggi: int, judul: string, sorot: list<array{label: int|string, x: float, y: float, teks: string}>}>|null */
    protected ?array $gambar = null;

    protected ?string $cap = null;

    public function berkas(): string
    {
        return base_path('docs/user-manual/aset/manifes.json');
    }

    /**
     * @return array{lebar: int, tinggi: int, judul: string, sorot: list<array{label: int|string, x: float, y: float, teks: string}>}|null
     */
    public function untuk(string $berkas): ?array
    {
        return $this->muat()[$berkas] ?? null;
    }

    /** Cap isi manifes, dipakai sebagai bagian kunci cache render. */
    public function cap(): string
    {
        if ($this->cap !== null) {
            return $this->cap;
        }

        $berkas = $this->berkas();

        return $this->cap = File::exists($berkas)
            ? substr((string) hash_file('xxh128', $berkas), 0, 16)
            : 'kosong';
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function muat(): array
    {
        if ($this->gambar !== null) {
            return $this->gambar;
        }

        $berkas = $this->berkas();

        if (! File::exists($berkas)) {
            return $this->gambar = [];
        }

        $isi = json_decode(File::get($berkas), true);

        return $this->gambar = is_array($isi['gambar'] ?? null) ? $isi['gambar'] : [];
    }
}
