<?php

namespace App\Modules\Simulasi\Support;

/**
 * Definisi akun dan unit milik satu sandbox simulasi.
 *
 * Setiap sandbox memiliki pohon unit (Universitas → Fakultas → Prodi Simulasi)
 * dan akun sendiri. Agar kolom unik (username, email, nidn, kode_pddikti, nim)
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

    /** @var array<string, string> */
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
     * Enam peran × tiga tingkat. Kuncinya adalah nama dasar; username nyata
     * ditambah akhiran sandbox lewat username().
     *
     * @return array<string, array{nama: string, peran: string, unit: string, jabatan: string, pimpinan: bool, tim_kurikulum: bool}>
     */
    public static function akun(): array
    {
        $buat = static fn (string $nama, string $peran, string $unit, string $jabatan, bool $pimpinan = false, bool $timKurikulum = false): array => [
            'nama' => $nama, 'peran' => $peran, 'unit' => $unit, 'jabatan' => $jabatan,
            'pimpinan' => $pimpinan, 'tim_kurikulum' => $timKurikulum,
        ];

        return [
            'sim-adminuniv' => $buat('Admin Universitas (Simulasi)', 'Admin', 'univ', 'Admin Universitas'),
            'sim-adminfak' => $buat('Admin Fakultas (Simulasi)', 'Admin', 'fak', 'Admin Fakultas'),
            'sim-adminprodi' => $buat('Admin Program Studi (Simulasi)', 'Admin', 'prodi', 'Admin Program Studi'),

            'sim-timkuruniv' => $buat('Tim Kurikulum Universitas (Simulasi)', 'Tim Kurikulum', 'univ', 'Tim Kurikulum', timKurikulum: true),
            'sim-timkurfak' => $buat('Tim Kurikulum Fakultas (Simulasi)', 'Tim Kurikulum', 'fak', 'Tim Kurikulum', timKurikulum: true),
            'sim-timkur' => $buat('Tim Kurikulum Program Studi (Simulasi)', 'Tim Kurikulum', 'prodi', 'Tim Kurikulum', timKurikulum: true),

            'sim-kormauniv' => $buat('Koordinator MK Universitas (Simulasi)', 'Koordinator Mata Kuliah', 'univ', 'Koordinator MK'),
            'sim-kormafak' => $buat('Koordinator MK Fakultas (Simulasi)', 'Koordinator Mata Kuliah', 'fak', 'Koordinator MK'),
            'sim-korma' => $buat('Koordinator MK Program Studi (Simulasi)', 'Koordinator Mata Kuliah', 'prodi', 'Koordinator MK'),

            'sim-dosenuniv' => $buat('Dosen Pengampu Universitas (Simulasi)', 'Dosen Pengampu', 'univ', 'Dosen'),
            'sim-dosenfak' => $buat('Dosen Pengampu Fakultas (Simulasi)', 'Dosen Pengampu', 'fak', 'Dosen'),
            'sim-dosen' => $buat('Dosen Pengampu Program Studi (Simulasi)', 'Dosen Pengampu', 'prodi', 'Dosen'),

            'sim-rektor' => $buat('Rektor (Simulasi)', 'Pimpinan', 'univ', 'Rektor', pimpinan: true),
            'sim-dekan' => $buat('Dekan (Simulasi)', 'Pimpinan', 'fak', 'Dekan', pimpinan: true),
            'sim-kaprodi' => $buat('Ketua Program Studi (Simulasi)', 'Pimpinan', 'prodi', 'Ketua Program Studi', pimpinan: true),

            'sim-auditoruniv' => $buat('Auditor Mutu Universitas (Simulasi)', 'Auditor Mutu', 'univ', 'Auditor Mutu'),
            'sim-auditorfak' => $buat('Auditor Mutu Fakultas (Simulasi)', 'Auditor Mutu', 'fak', 'Auditor Mutu'),
            'sim-auditor' => $buat('Auditor Mutu Program Studi (Simulasi)', 'Auditor Mutu', 'prodi', 'Auditor Mutu'),
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
     * Tanpa 'super-admin': jalur masuk tanpa kata sandi tidak boleh pernah
     * mencapai peran itu, terlepas dari apakah slugnya ditampilkan atau tidak.
     *
     * @return array<string, array<string, string>>
     */
    public static function akunPanduan(): array
    {
        $petakan = static function (array $kunciPerLevel): array {
            return $kunciPerLevel;
        };

        return [
            'admin-unit' => $petakan(['univ' => 'sim-adminuniv', 'fak' => 'sim-adminfak', 'prodi' => 'sim-adminprodi']),
            'tim-kurikulum' => $petakan(['univ' => 'sim-timkuruniv', 'fak' => 'sim-timkurfak', 'prodi' => 'sim-timkur']),
            'koordinator-mk' => $petakan(['univ' => 'sim-kormauniv', 'fak' => 'sim-kormafak', 'prodi' => 'sim-korma']),
            'dosen-pengampu' => $petakan(['univ' => 'sim-dosenuniv', 'fak' => 'sim-dosenfak', 'prodi' => 'sim-dosen']),
            'pimpinan' => $petakan(['univ' => 'sim-rektor', 'fak' => 'sim-dekan', 'prodi' => 'sim-kaprodi']),
            'auditor-mutu' => $petakan(['univ' => 'sim-auditoruniv', 'fak' => 'sim-auditorfak', 'prodi' => 'sim-auditor']),
        ];
    }
}
