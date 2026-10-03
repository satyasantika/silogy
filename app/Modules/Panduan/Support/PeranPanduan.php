<?php

namespace App\Modules\Panduan\Support;

use App\Modules\Simulasi\Support\AkunSimulasi;

/**
 * Peta peran → berkas Markdown → akun latihan.
 *
 * Markdown di docs/user-manual/ adalah satu-satunya sumber isi manual. Halaman
 * web merendernya langsung, jadi tidak ada lagi dua salinan yang bisa saling
 * menyimpang seperti dulu ketika halaman HTML digenerate skrip Python
 * terpisah dari berkas .md-nya.
 */
final class PeranPanduan
{
    /**
     * @return array<string, array{berkas: string, label: string, ringkas: string, ikon: string}>
     */
    public static function semua(): array
    {
        return [
            // 'super-admin' sengaja disembunyikan dari peta peran publik: Boss
            // minta halaman panduan dan tombol "Coba sebagai" untuk peran ini
            // tidak tampil ke pengunjung anonim sampai diminta tampil kembali.
            // Berkas 01-super-admin.md tetap ada di docs/user-manual/, jadi
            // memulihkannya tinggal mengembalikan entri ini.
            'admin-unit' => [
                'berkas' => '02-admin-unit.md',
                'label' => 'Admin Unit',
                'ringkas' => 'Pengguna dan operasional di unit Anda, sampai kelas di prodi.',
                'ikon' => 'bi-people',
            ],
            'tim-kurikulum' => [
                'berkas' => '03-tim-kurikulum.md',
                'label' => 'Tim Kurikulum',
                'ringkas' => 'Profil lulusan, CPL, BoK, mata kuliah, dan persetujuan CPMK.',
                'ikon' => 'bi-diagram-3',
            ],
            'koordinator-mk' => [
                'berkas' => '04-koordinator-mk.md',
                'label' => 'Koordinator MK',
                'ringkas' => 'CPMK, Sub-CPMK, dan komponen asesmen per semester.',
                'ikon' => 'bi-journal-check',
            ],
            'dosen-pengampu' => [
                'berkas' => '05-dosen-pengampu.md',
                'label' => 'Dosen Pengampu',
                'ringkas' => 'Mengisi nilai kelas yang Anda ampu.',
                'ikon' => 'bi-pencil-square',
            ],
            'pimpinan' => [
                'berkas' => '06-pimpinan.md',
                'label' => 'Pimpinan',
                'ringkas' => 'Dasbor capaian dan laporan CPL di unit Anda.',
                'ikon' => 'bi-graph-up-arrow',
            ],
            'auditor-mutu' => [
                'berkas' => '07-auditor-mutu.md',
                'label' => 'Auditor Mutu',
                'ringkas' => 'Membaca laporan dan jejak perubahan, tanpa mengubah data.',
                'ikon' => 'bi-clipboard-check',
            ],
            'alur-end-to-end' => [
                'berkas' => '08-simulasi-penggunaan.md',
                'label' => 'Alur End-to-End',
                'ringkas' => 'Urutan lengkap dari nol sampai laporan, melintasi semua peran.',
                'ikon' => 'bi-signpost-split',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function slug(): array
    {
        return array_keys(self::semua());
    }

    /**
     * @return array{berkas: string, label: string, ringkas: string, ikon: string}|null
     */
    public static function definisi(string $slug): ?array
    {
        return self::semua()[$slug] ?? null;
    }

    /**
     * Kunci akun simulasi untuk tombol "Coba sebagai ‹peran›" pada tingkat
     * tertentu, bila ada. Username sebenarnya ditambah akhiran sandbox.
     */
    public static function akunUntuk(string $slug, string $level = 'prodi'): ?string
    {
        return AkunSimulasi::akunPanduan()[$slug][$level] ?? null;
    }

    /**
     * Peran aktif yang harus dipasang setelah masuk otomatis.
     */
    public static function peranUntuk(string $slug): ?string
    {
        $kunci = self::akunUntuk($slug);

        return $kunci === null ? null : (AkunSimulasi::akun()[$kunci]['peran'] ?? null);
    }

    /**
     * Peran yang punya tombol "Coba sebagai" (bukan alur end-to-end).
     *
     * @return array<string, array{berkas: string, label: string, ringkas: string, ikon: string}>
     */
    public static function bisaDicoba(): array
    {
        return array_intersect_key(self::semua(), AkunSimulasi::akunPanduan());
    }
}
