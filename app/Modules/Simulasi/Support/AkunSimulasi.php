<?php

namespace App\Modules\Simulasi\Support;

/**
 * Daftar akun dan unit milik simulasi.
 *
 * Akun simulasi sengaja memakai ruang nama `sim-` yang terpisah dari akun demo
 * lama (`timkur`, `korma`, `dosen`, …). Dua alasannya:
 *
 *  1. Akun demo lama sudah ada di basis data, sehingga di bawah invarian
 *     "hanya catat yang benar-benar dibuat" mereka selalu berstatus dipinjam —
 *     gerbang masuk-otomatis akan menolak semuanya dan tombol "Coba sebagai"
 *     mati sejak lahir.
 *  2. Menyeragamkan akun lama berarti menjalankan RolePermissionSeeder dari
 *     sebuah tombol antarmuka, yang akan mereset kata sandi dan menimpa peran
 *     pengguna nyata yang kebetulan memakai username sama.
 *
 * Unit simulasi juga berdiri sebagai pohon sendiri (Universitas Simulasi →
 * Fakultas Simulasi → Prodi Simulasi), bukan menumpang unit nyata. Dengan
 * begitu seluruh policy berbasis AcademicUnitScope otomatis memagari pengunjung
 * simulasi dari data nyata, dan agregasi CPL-nya tidak pernah mengotori laporan
 * fakultas/universitas yang sesungguhnya.
 */
final class AkunSimulasi
{
    public const SANDI = 'siliwangi';

    public const DOMAIN = 'simulasi.silogy.test';

    public const AWALAN_USERNAME = 'sim-';

    public const AWALAN_KODE_UNIT = 'SIM-';

    /**
     * Pohon unit milik simulasi, terurut dari akar ke daun.
     *
     * @return array<string, array{code: string, nama: string, type: string, induk: ?string, tambahan: array<string, mixed>}>
     */
    public static function unit(): array
    {
        return [
            'univ' => [
                'code' => 'SIM-UNIV',
                'nama' => 'Universitas Simulasi',
                'type' => 'university',
                'induk' => null,
                'tambahan' => ['kode_pddikti' => 'SIM0001', 'status' => 'aktif'],
            ],
            'fak' => [
                'code' => 'SIM-FAK',
                'nama' => 'Fakultas Simulasi',
                'type' => 'faculty',
                'induk' => 'univ',
                'tambahan' => ['status' => 'aktif'],
            ],
            'prodi' => [
                'code' => 'SIM-PRODI',
                'nama' => 'Program Studi Simulasi',
                'type' => 'study_program',
                'induk' => 'fak',
                'tambahan' => [
                    'kode_pddikti' => 'SIM84202',
                    'jenjang' => 'S1',
                    'gelar_lulusan' => 'S.Sim.',
                    'status' => 'aktif',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{nama: string, peran: string, unit: ?string, jabatan: string, pimpinan: bool, tim_kurikulum: bool, nidn: string}>
     */
    public static function akun(): array
    {
        return [
            'sim-superadmin' => ['nama' => 'Super Admin (Simulasi)', 'peran' => 'Super Admin',
                'unit' => null, 'jabatan' => 'Super Admin', 'pimpinan' => false, 'tim_kurikulum' => false, 'nidn' => '9900000001'],

            'sim-adminuniv' => ['nama' => 'Admin Universitas (Simulasi)', 'peran' => 'Admin',
                'unit' => 'univ', 'jabatan' => 'Admin Universitas', 'pimpinan' => false, 'tim_kurikulum' => false, 'nidn' => '9900000002'],
            'sim-adminfak' => ['nama' => 'Admin Fakultas (Simulasi)', 'peran' => 'Admin',
                'unit' => 'fak', 'jabatan' => 'Admin Fakultas', 'pimpinan' => false, 'tim_kurikulum' => false, 'nidn' => '9900000003'],
            'sim-adminprodi' => ['nama' => 'Admin Program Studi (Simulasi)', 'peran' => 'Admin',
                'unit' => 'prodi', 'jabatan' => 'Admin Program Studi', 'pimpinan' => false, 'tim_kurikulum' => false, 'nidn' => '9900000004'],

            'sim-timkur' => ['nama' => 'Tim Kurikulum (Simulasi)', 'peran' => 'Tim Kurikulum',
                'unit' => 'prodi', 'jabatan' => 'Tim Kurikulum', 'pimpinan' => false, 'tim_kurikulum' => true, 'nidn' => '9900000005'],
            'sim-korma' => ['nama' => 'Koordinator Mata Kuliah (Simulasi)', 'peran' => 'Koordinator Mata Kuliah',
                'unit' => 'prodi', 'jabatan' => 'Koordinator MK', 'pimpinan' => false, 'tim_kurikulum' => false, 'nidn' => '9900000006'],

            'sim-dosen' => ['nama' => 'Dosen Pengampu (Simulasi)', 'peran' => 'Dosen Pengampu',
                'unit' => 'prodi', 'jabatan' => 'Dosen', 'pimpinan' => false, 'tim_kurikulum' => false, 'nidn' => '9900000007'],
            'sim-dosenfak' => ['nama' => 'Dosen Pengampu Fakultas (Simulasi)', 'peran' => 'Dosen Pengampu',
                'unit' => 'fak', 'jabatan' => 'Dosen', 'pimpinan' => false, 'tim_kurikulum' => false, 'nidn' => '9900000008'],
            'sim-dosenuniv' => ['nama' => 'Dosen Pengampu Universitas (Simulasi)', 'peran' => 'Dosen Pengampu',
                'unit' => 'univ', 'jabatan' => 'Dosen', 'pimpinan' => false, 'tim_kurikulum' => false, 'nidn' => '9900000009'],

            'sim-kaprodi' => ['nama' => 'Ketua Program Studi (Simulasi)', 'peran' => 'Pimpinan',
                'unit' => 'prodi', 'jabatan' => 'Ketua Program Studi', 'pimpinan' => true, 'tim_kurikulum' => false, 'nidn' => '9900000010'],
            'sim-dekan' => ['nama' => 'Dekan (Simulasi)', 'peran' => 'Pimpinan',
                'unit' => 'fak', 'jabatan' => 'Dekan', 'pimpinan' => true, 'tim_kurikulum' => false, 'nidn' => '9900000011'],
            'sim-rektor' => ['nama' => 'Rektor (Simulasi)', 'peran' => 'Pimpinan',
                'unit' => 'univ', 'jabatan' => 'Rektor', 'pimpinan' => true, 'tim_kurikulum' => false, 'nidn' => '9900000012'],

            'sim-auditor' => ['nama' => 'Auditor Mutu (Simulasi)', 'peran' => 'Auditor Mutu',
                'unit' => null, 'jabatan' => 'Auditor Mutu', 'pimpinan' => false, 'tim_kurikulum' => false, 'nidn' => '9900000013'],
        ];
    }

    public static function surel(string $username): string
    {
        return $username.'@'.self::DOMAIN;
    }

    /**
     * Akun yang dipakai tombol "Coba sebagai ‹peran›" pada tiap halaman panduan.
     *
     * @return array<string, string> slug panduan => username
     */
    public static function akunPanduan(): array
    {
        return [
            // Tanpa pemetaan untuk 'super-admin': jalur masuk-otomatis TANPA
            // KATA SANDI tidak boleh pernah bisa dipakai memasuki peran ini,
            // terlepas dari apakah slugnya nanti dimunculkan lagi di
            // PeranPanduan::semua(). Siapa pun yang butuh peran Super Admin
            // wajib login sungguhan dengan kredensial asli.
            'admin-unit' => 'sim-adminprodi',
            'tim-kurikulum' => 'sim-timkur',
            'koordinator-mk' => 'sim-korma',
            'dosen-pengampu' => 'sim-dosen',
            'pimpinan' => 'sim-kaprodi',
            'auditor-mutu' => 'sim-auditor',
        ];
    }
}
