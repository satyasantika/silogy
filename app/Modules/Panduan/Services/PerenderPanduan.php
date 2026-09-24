<?php

namespace App\Modules\Panduan\Services;

use App\Modules\Panduan\DataObjects\HalamanPanduan;
use App\Modules\Panduan\Support\PeranPanduan;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Merender satu berkas Markdown manual menjadi HTML halaman panduan.
 *
 * Pasca-pemrosesan memakai DOMDocument, bukan regex: berkas manual memuat
 * tabel GFM, blok kode ASCII, dan `<b>` di dalam legenda gambar — semuanya
 * cepat membuat pencocokan pola meleset.
 */
class PerenderPanduan
{
    public function __construct(protected ManifesTangkapanLayar $manifes) {}

    public function render(string $slug): ?HalamanPanduan
    {
        $definisi = PeranPanduan::definisi($slug);

        if ($definisi === null) {
            return null;
        }

        $berkas = base_path('docs/user-manual/'.$definisi['berkas']);

        if (! File::exists($berkas)) {
            return null;
        }

        // Kunci cache memuat cap isi berkas DAN cap manifes: menyunting
        // Markdown atau menangkap ulang layar otomatis membuat cache basi,
        // tanpa perlu `cache:clear` sama sekali.
        $kunci = sprintf(
            'panduan:v1:%s:%s:%s',
            $slug,
            substr((string) hash_file('xxh128', $berkas), 0, 16),
            $this->manifes->cap(),
        );

        $bangun = fn (): array => $this->bangun($slug, $definisi, $berkas);

        $data = config('app.debug')
            ? $bangun()
            : Cache::remember($kunci, now()->addWeek(), $bangun);

        return new HalamanPanduan(
            slug: $slug,
            judul: $data['judul'],
            isi: $data['isi'],
            daftarIsi: $data['daftarIsi'],
            jumlahGambar: $data['jumlahGambar'],
        );
    }

    /**
     * @param  array{berkas: string, label: string, ringkas: string, ikon: string}  $definisi
     * @return array{judul: string, isi: string, daftarIsi: list<array{taraf: int, id: string, teks: string}>, jumlahGambar: int}
     */
    protected function bangun(string $slug, array $definisi, string $berkas): array
    {
        $markdown = File::get($berkas);

        // Str::markdown() Laravel memakai GithubFlavoredMarkdownConverter,
        // jadi tabel pada 03- dan 04- ter-render tanpa ekstensi tambahan.
        $html = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $sebelumnya = libxml_use_internal_errors(true);
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="pd-akar">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($sebelumnya);

        $xpath = new DOMXPath($dom);

        $judul = $this->angkatJudul($dom, $xpath, $definisi['label']);
        $daftarIsi = $this->beriJangkar($xpath);
        $jumlahGambar = $this->ubahGambar($dom, $xpath);
        $this->perbaikiTautan($xpath);

        $akar = $dom->getElementById('pd-akar');
        $isi = '';

        foreach ($akar === null ? [] : iterator_to_array($akar->childNodes) as $anak) {
            $isi .= $dom->saveHTML($anak);
        }

        return [
            'judul' => $judul,
            'isi' => $isi,
            'daftarIsi' => $daftarIsi,
            'jumlahGambar' => $jumlahGambar,
        ];
    }

    /** Judul <h1> dipindah ke kepala halaman, tidak diulang di badan teks. */
    protected function angkatJudul(DOMDocument $dom, DOMXPath $xpath, string $cadangan): string
    {
        $h1 = $xpath->query('//h1')->item(0);

        if (! $h1 instanceof DOMElement) {
            return $cadangan;
        }

        $judul = trim($h1->textContent);
        $h1->parentNode?->removeChild($h1);

        return $judul === '' ? $cadangan : $judul;
    }

    /**
     * @return list<array{taraf: int, id: string, teks: string}>
     */
    protected function beriJangkar(DOMXPath $xpath): array
    {
        $daftar = [];
        $dipakai = [];

        foreach ($xpath->query('//h2 | //h3') as $tajuk) {
            if (! $tajuk instanceof DOMElement) {
                continue;
            }

            $teks = trim($tajuk->textContent);
            $id = Str::slug($teks) ?: 'bagian';

            if (isset($dipakai[$id])) {
                $id .= '-'.(++$dipakai[$id]);
            } else {
                $dipakai[$id] = 1;
            }

            $tajuk->setAttribute('id', $id);
            $tajuk->setAttribute('class', 'pd-tajuk');

            $daftar[] = [
                'taraf' => (int) substr($tajuk->nodeName, 1),
                'id' => $id,
                'teks' => $teks,
            ];
        }

        return $daftar;
    }

    /**
     * Ubah tiap <img> menjadi figur beranotasi: gambar bersih + badge bernomor
     * berposisi persen + legenda.
     *
     * Koordinat disimpan sebagai persentase ukuran intrinsik gambar, jadi badge
     * tetap menempel pada elemen yang benar di lebar layar berapa pun — itulah
     * alasan angka TIDAK dibakar ke dalam PNG.
     */
    protected function ubahGambar(DOMDocument $dom, DOMXPath $xpath): int
    {
        $jumlah = 0;

        foreach (iterator_to_array($xpath->query('//img')) as $img) {
            if (! $img instanceof DOMElement) {
                continue;
            }

            $src = $img->getAttribute('src');
            $alt = $img->getAttribute('alt');
            $berkas = basename($src);
            $entri = $this->manifes->untuk($berkas);

            $figure = $dom->createElement('figure');
            $figure->setAttribute('class', 'pd-gbr');

            $url = $this->urlGambar($berkas);

            $bingkai = $dom->createElement('a');
            $bingkai->setAttribute('class', 'pd-bingkai');
            $bingkai->setAttribute('href', $url);
            $bingkai->setAttribute('target', '_blank');
            $bingkai->setAttribute('rel', 'noopener');

            $gambar = $dom->createElement('img');
            $gambar->setAttribute('src', $url);
            $gambar->setAttribute('alt', $alt);
            $gambar->setAttribute('loading', 'lazy');
            $gambar->setAttribute('decoding', 'async');

            // Lebar/tinggi intrinsik wajib ada: tanpanya kotak gambar bertinggi
            // nol sampai berkas selesai diunduh, dan seluruh badge menumpuk di
            // pojok kiri atas sebelum tata letak menetap.
            if ($entri !== null) {
                $gambar->setAttribute('width', (string) $entri['lebar']);
                $gambar->setAttribute('height', (string) $entri['tinggi']);
            }

            $bingkai->appendChild($gambar);

            foreach ($entri['sorot'] ?? [] as $sorot) {
                $badge = $dom->createElement('span', (string) $sorot['label']);
                $badge->setAttribute('class', 'pd-badge');
                $badge->setAttribute('style', sprintf('left:%s%%;top:%s%%', $sorot['x'], $sorot['y']));
                $badge->setAttribute('aria-hidden', 'true');
                $bingkai->appendChild($badge);
            }

            $figure->appendChild($bingkai);

            if ($alt !== '') {
                $caption = $dom->createElement('figcaption');
                $caption->appendChild($dom->createTextNode($alt));
                $figure->appendChild($caption);
            }

            $legenda = array_values(array_filter(
                $entri['sorot'] ?? [],
                fn (array $s): bool => $s['teks'] !== '',
            ));

            if ($legenda !== []) {
                $ol = $dom->createElement('ol');
                $ol->setAttribute('class', 'pd-legenda');

                foreach ($legenda as $sorot) {
                    $li = $dom->createElement('li');
                    $nomor = $dom->createElement('span', (string) $sorot['label']);
                    $nomor->setAttribute('class', 'pd-nomor');
                    $li->appendChild($nomor);

                    $potongan = $dom->createDocumentFragment();
                    $potongan->appendXML('<span>'.$sorot['teks'].'</span>');
                    $li->appendChild($potongan);

                    $ol->appendChild($li);
                }

                $figure->appendChild($ol);
            }

            // Markdown membungkus gambar tunggal dalam <p>; <figure> di dalam
            // <p> tidak sah, jadi pembungkusnya ikut diganti.
            $induk = $img->parentNode;
            $sasaran = ($induk instanceof DOMElement && $induk->nodeName === 'p' && $induk->childNodes->length === 1)
                ? $induk
                : $img;

            $sasaran->parentNode?->replaceChild($figure, $sasaran);
            $jumlah++;
        }

        return $jumlah;
    }

    /**
     * nginx menangkap permintaan berakhiran .png sebelum sampai ke PHP, jadi
     * jalur yang dipakai adalah public/manual (ditautkan `panduan:tautkan-aset`).
     * Rute Laravel tetap dipertahankan sebagai cadangan untuk peladen yang
     * memang meneruskan semuanya ke PHP, mis. `php artisan serve` saat ngoprek.
     */
    protected function urlGambar(string $berkas): string
    {
        return is_file(public_path('manual/'.$berkas))
            ? asset('manual/'.$berkas)
            : route('panduan.aset', ['berkas' => $berkas]);
    }

    /** Tautan antar-berkas .md diarahkan ke rute panduan; tautan luar diberi rel aman. */
    protected function perbaikiTautan(DOMXPath $xpath): void
    {
        $peta = [];

        foreach (PeranPanduan::semua() as $slug => $definisi) {
            $peta[$definisi['berkas']] = route('panduan.peran', ['peran' => $slug]);
        }

        foreach ($xpath->query('//a[@href]') as $a) {
            if (! $a instanceof DOMElement) {
                continue;
            }

            $href = $a->getAttribute('href');

            if (isset($peta[$href])) {
                $a->setAttribute('href', $peta[$href]);

                continue;
            }

            if (str_starts_with($href, 'html/index.html') || $href === '00-README.md') {
                $a->setAttribute('href', route('panduan.indeks'));

                continue;
            }

            if (Str::startsWith($href, ['http://', 'https://'])) {
                $a->setAttribute('target', '_blank');
                $a->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }
}
