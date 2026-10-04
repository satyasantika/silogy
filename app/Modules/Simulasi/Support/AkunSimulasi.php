<?php

namespace App\Modules\Simulasi\Support;

/**
 * Definisi akun dan unit milik satu sandbox simulasi.
 *
 * Simulasi dipusatkan pada tingkat Program Studi. Setiap sandbox memiliki satu
 * prodi simulasi beserta akun sendiri. Prodi tidak boleh tanpa induk, sehingga
 * fakultas dan universitas dibuatkan sebagai wadah kosong (tanpa akun, tanpa
 * data akademik); pengunjung tidak pernah melihat atau mengurusnya. Agar kolom unik (username, email, nidn, kode_pddikti, nim)
 * tidak pernah bentrok antarsandbox maupun dengan data inti, semua identitas
 * diberi akhiran `kode` yang diturunkan dari ID sandbox. Akhiran itu pula
 * yang menghapus penyebab utama kegagalan bangun-ulang: sisa data dari
 * pembangunan yang gagal tidak lagi "dipungut" oleh firstOrCreate.
 *
 * Kata sandi akun sandbox acak dan tidak pernah ditampilkan. Satu-satunya
 * jalan masuk adalah tombol "Coba sebagai ‹peran›" yang memakai tiket sekali
 * pakai (lihat CobaPeranController); akun Super Admin sengaja tidak ada.
 */
final class AkunSimulasi
{
    public const DOMAIN = 'simulasi.silogy.test';

    public const AWALAN_USERNAME = 'sim-';

    public const AWALAN_KODE_UNIT = 'SIM-';

    /** Tingkat yang punya simulasi. Tingkat lain hanya punya panduan. */
    public const LEVEL_SIMULASI = 'prodi';

    /** @var array<string, string> panduan dibaca per tingkat; simulasi hanya untuk LEVEL_SIMULASI */
    public const LEVEL = [
        'univ' => 'Universitas',
        'fak' => 'Fakultas',
        'prodi' => 'Program Studi',
    ];

    /**
     * Pohon unit satu sandbox, terurut dari akar ke daun.
     *
     * @return array<string, array{code: string, nama: string, type: string, induk: ?string, tambahan: array<string, mixed>}>
     */
    public static function unit(string $kode): array
    {
        return [
            'univ' => [
                'code' => 'SIM-UNIV-'.$kode,
                'nama' => 'Universitas Simulasi',
                'type' => 'university',
                'induk' => null,
                'tambahan' => ['kode_pddikti' => 'SIM-U-'.$kode, 'status' => 'aktif'],
            ],
            'fak' => [
                'code' => 'SIM-FAK-'.$kode,
                'nama' => 'Fakultas Simulasi',
                'type' => 'faculty',
                'induk' => 'univ',
                'tambahan' => ['status' => 'aktif'],
            ],
            'prodi' => [
                'code' => 'SIM-PRODI-'.$kode,
                'nama' => 'Program Studi Simulasi',
                'type' => 'study_program',
                'induk' => 'fak',
                'tambahan' => [
                    'kode_pddikti' => 'SIM-P-'.$kode,
                    'jenjang' => 'S1',
                    'gelar_lulusan' => 'S.Sim.',
                    'status' => 'aktif',
                ],
            ],
        ];
    }

    /**
     * Enam peran pada tingkat Program Studi. Kuncinya adalah nama dasar;
     * username nyata ditambah akhiran sandbox lewat username().
     *
     * @return array<string, array{nama: string, peran: string, unit: string, jabatan: string, pimpinan: bool, tim_kurikulum: bool}>
     */
    public static function akun(): array
    {
        $buat = static fn (string $nama, string $peran, string $jabatan, bool $pimpinan = false, bool $timKurikulum = false): array => [
            'nama' => $nama, 'peran' => $peran, 'unit' => 'prodi', 'jabatan' => $jabatan,
            'pimpinan' => $pimpinan, 'tim_kurikulum' => $timKurikulum,
        ];

        return [
            'sim-adminprodi' => $buat('Admin Program Studi (Simulasi)', 'Admin', 'Admin Program Studi'),
            'sim-timkur' => $buat('Tim Kurikulum Program Studi (Simulasi)', 'Tim Kurikulum', 'Tim Kurikulum', timKurikulum: true),
            'sim-korma' => $buat('Koordinator MK Program Studi (Simulasi)', 'Koordinator Mata Kuliah', 'Koordinator MK'),
            'sim-dosen' => $buat('Dosen Pengampu Program Studi (Simulasi)', 'Dosen Pengampu', 'Dosen'),
            'sim-kaprodi' => $buat('Ketua Program Studi (Simulasi)', 'Pimpinan', 'Ketua Program Studi', pimpinan: true),
            'sim-auditor' => $buat('Auditor Mutu Program Studi (Simulasi)', 'Auditor Mutu', 'Auditor Mutu'),
        ];
    }

    /** Akhiran 8 karakter heksadesimal dari ID sandbox (bagian acak UUID). */
    public static function kode(string $sandboxId): string
    {
        return substr(str_replace('-', '', strtolower($sandboxId)), -8);
    }

    public static function username(string $kunci, string $kode): string
    {
        return $kunci.'-'.$kode;
    }

    public static function surel(string $username): string
    {
        return $username.'@'.self::DOMAIN;
    }

    /** NIDN 10 digit unik per akun dan sandbox: "99" + 6 digit hash + 2 digit urutan. */
    public static function nidn(string $kunci, string $kode): string
    {
        $urutan = array_search($kunci, array_keys(self::akun()), true);
        $hash = str_pad((string) (crc32($kode) % 1_000_000), 6, '0', STR_PAD_LEFT);

        return '99'.$hash.str_pad((string) (((int) $urutan) + 1), 2, '0', STR_PAD_LEFT);
    }

    /** NIM unik per mahasiswa dan sandbox (maks. 20 karakter). */
    public static function nim(string $kode, int $urutan): string
    {
        return 'S'.$kode.str_pad((string) $urutan, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Peta tombol "Coba sebagai ‹peran›": slug panduan → tingkat → kunci akun.
     * Hanya tingkat Program Studi yang punya simulasi; panduan tingkat lain
     * tetap dibaca tanpa tombol coba.
     * Tanpa 'super-admin': jalur masuk tanpa kata sandi tidak boleh pernah
     * mencapai peran itu, terlepas dari apakah slugnya ditampilkan atau tidak.
     *
     * @return array<string, array<string, string>>
     */
    public static function akunPanduan(): array
    {
        return [
            'admin-unit' => ['prodi' => 'sim-adminprodi'],
            'tim-kurikulum' => ['prodi' => 'sim-timkur'],
            'koordinator-mk' => ['prodi' => 'sim-korma'],
            'dosen-pengampu' => ['prodi' => 'sim-dosen'],
            'pimpinan' => ['prodi' => 'sim-kaprodi'],
            'auditor-mutu' => ['prodi' => 'sim-auditor'],
        ];
    }
}
