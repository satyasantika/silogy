<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        // =========================================================
        // 1. PERMISSIONS
        // =========================================================
        $perms = [
            // ---- Institusi ----
            'kelola_universitas', 'kelola_fakultas', 'kelola_jurusan', 'kelola_prodi',
            'kelola_semester', 'kelola_evaluasi',

            // ---- Admin & Auth ----
            'kelola_user', 'kelola_role', 'kelola_permission',
            'lihat_audit_log', 'konfigurasi_sistem',

            // ---- User-management per tipe unit ----
            'kelola_user_universitas', 'kelola_user_fakultas',
            'kelola_user_jurusan', 'kelola_user_prodi',

            // ---- Kurikulum ----
            'kelola_kurikulum', 'kelola_profil_lulusan',
            'kelola_cpl', 'kelola_bok', 'kelola_mk', 'kelola_mk_unit',

            // ---- CPMK / SubCPMK / Komponen ----
            'kelola_cpmk', 'kelola_subcpmk', 'kelola_komponen_penilaian',

            // ---- Kelas & Penilaian ----
            'kelola_kelas', 'setdosen_mk', 'input_nilai', 'import_nilai',

            // ---- Laporan & AI ----
            'lihat_laporan', 'ekspor_data', 'minta_analisis_ai', 'lihat_dashboard',
        ];

        foreach ($perms as $p) {
            Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
        }

        // =========================================================
        // 2. ROLES
        // =========================================================

        // --- Super Admin: HANYA Institusi & Admin + kelola_evaluasi ---
        $super = Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $super->syncPermissions([
            'kelola_universitas', 'kelola_fakultas', 'kelola_jurusan', 'kelola_prodi',
            'kelola_semester', 'kelola_evaluasi',
            'kelola_user', 'kelola_role', 'kelola_permission',
            'lihat_audit_log', 'konfigurasi_sistem',
            'kelola_user_universitas', 'kelola_user_fakultas',
            'kelola_user_jurusan', 'kelola_user_prodi',
        ]);

        // --- Admin per Tipe Unit (kelola sesuai type_unit) ---
        $adminUniv = Role::firstOrCreate(['name' => 'Admin Universitas', 'guard_name' => 'web']);
        $adminUniv->syncPermissions([
            'kelola_user_universitas', 'kelola_user_fakultas',
            'kelola_user_jurusan', 'kelola_user_prodi',
            'kelola_fakultas', 'kelola_jurusan', 'kelola_prodi',
            'setdosen_mk',
            'lihat_laporan', 'ekspor_data', 'lihat_dashboard',
        ]);

        $adminFak = Role::firstOrCreate(['name' => 'Admin Fakultas', 'guard_name' => 'web']);
        $adminFak->syncPermissions([
            'kelola_user_fakultas', 'kelola_user_jurusan', 'kelola_user_prodi',
            'kelola_jurusan', 'kelola_prodi',
            'setdosen_mk',
            'lihat_laporan', 'ekspor_data', 'lihat_dashboard',
        ]);

        $adminJur = Role::firstOrCreate(['name' => 'Admin Jurusan', 'guard_name' => 'web']);
        $adminJur->syncPermissions([
            'kelola_user_jurusan', 'kelola_user_prodi',
            'kelola_prodi',
            'setdosen_mk',
            'lihat_laporan', 'ekspor_data', 'lihat_dashboard',
        ]);

        $adminProdi = Role::firstOrCreate(['name' => 'Admin Program Studi', 'guard_name' => 'web']);
        $adminProdi->syncPermissions([
            'kelola_user_prodi',
            'kelola_kelas',
            'setdosen_mk',
            'lihat_laporan', 'ekspor_data', 'lihat_dashboard',
        ]);

        // --- Tim Kurikulum (Profil khusus prodi; CPL→BoK→MK untuk semua unit) ---
        $timKur = Role::firstOrCreate(['name' => 'Tim Kurikulum', 'guard_name' => 'web']);
        $timKur->syncPermissions([
            'kelola_kurikulum',
            'kelola_profil_lulusan', // hanya berlaku ketika status_tim_kurikulum=1 pada unit study_program
            'kelola_cpl', 'kelola_bok', 'kelola_mk', 'kelola_mk_unit',
            'setdosen_mk',
            'lihat_laporan', 'lihat_dashboard',
        ]);

        // --- Koordinator Mata Kuliah ---
        $korma = Role::firstOrCreate(['name' => 'Koordinator Mata Kuliah', 'guard_name' => 'web']);
        $korma->syncPermissions([
            'kelola_cpmk', 'kelola_subcpmk', 'kelola_komponen_penilaian',
            'lihat_laporan', 'lihat_dashboard',
        ]);

        // --- Dosen Pengampu (TANPA kelola_komponen_penilaian) ---
        $dosen = Role::firstOrCreate(['name' => 'Dosen Pengampu', 'guard_name' => 'web']);
        $dosen->syncPermissions([
            'kelola_kelas', 'input_nilai', 'import_nilai',
            'lihat_laporan', 'lihat_dashboard',
        ]);

        // --- Pimpinan * (per tipe unit; permission seperti Kaprodi) ---
        $pimpinanPerms = [
            'lihat_laporan', 'ekspor_data', 'minta_analisis_ai', 'lihat_dashboard',
        ];
        foreach ([
            'Pimpinan Universitas', 'Pimpinan Fakultas',
            'Pimpinan Jurusan', 'Pimpinan Program Studi',
        ] as $rname) {
            Role::firstOrCreate(['name' => $rname, 'guard_name' => 'web'])->syncPermissions($pimpinanPerms);
        }

        // --- Auditor Mutu ---
        $auditor = Role::firstOrCreate(['name' => 'Auditor Mutu', 'guard_name' => 'web']);
        $auditor->syncPermissions([
            'lihat_laporan', 'ekspor_data', 'lihat_audit_log', 'lihat_dashboard',
        ]);

        // =========================================================
        // 3. AKUN SEMENTARA + PENETAPAN UNIT
        // =========================================================
        // Asumsi: AcademicUnitSeeder sudah dijalankan dan menyediakan
        //         minimal satu unit pada tiap level.
        $univ = AcademicUnit::where('type', 'university')->first();
        $fak = AcademicUnit::where('type', 'faculty')->first();
        $jur = AcademicUnit::where('type', 'department')->first();
        $prodi = AcademicUnit::where('type', 'study_program')->first();

        $accounts = [
            // Super
            ['username' => 'superadmin', 'full_name' => 'Super Administrator',
                'nidn' => null, 'role' => 'Super Admin', 'unit' => null,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Super Admin'],

            // Pimpinan Universitas
            ['username' => 'rektor', 'full_name' => 'Rektor',
                'nidn' => '0000000001', 'role' => 'Pimpinan Universitas', 'unit' => $univ,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Rektor'],
            ['username' => 'wakilrektor', 'full_name' => 'Wakil Rektor I',
                'nidn' => '0000000002', 'role' => 'Pimpinan Universitas', 'unit' => $univ,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Wakil Rektor I'],

            // Pimpinan Fakultas
            ['username' => 'dekan', 'full_name' => 'Dekan',
                'nidn' => '0000000003', 'role' => 'Pimpinan Fakultas', 'unit' => $fak,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Dekan'],
            ['username' => 'wakildekan', 'full_name' => 'Wakil Dekan I',
                'nidn' => '0000000004', 'role' => 'Pimpinan Fakultas', 'unit' => $fak,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Wakil Dekan I'],

            // Pimpinan Jurusan
            ['username' => 'kajur', 'full_name' => 'Ketua Jurusan',
                'nidn' => '0000000005', 'role' => 'Pimpinan Jurusan', 'unit' => $jur,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Ketua Jurusan'],
            ['username' => 'sekjur', 'full_name' => 'Sekretaris Jurusan',
                'nidn' => '0000000006', 'role' => 'Pimpinan Jurusan', 'unit' => $jur,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Sekretaris Jurusan'],

            // Pimpinan Prodi
            ['username' => 'kaprodi', 'full_name' => 'Ketua Program Studi',
                'nidn' => '0000000007', 'role' => 'Pimpinan Program Studi', 'unit' => $prodi,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Ketua Program Studi'],

            // Admin per unit
            ['username' => 'adminuniv', 'full_name' => 'Admin Universitas',
                'nidn' => '0000000010', 'role' => 'Admin Universitas', 'unit' => $univ,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],
            ['username' => 'adminfak', 'full_name' => 'Admin Fakultas',
                'nidn' => '0000000011', 'role' => 'Admin Fakultas', 'unit' => $fak,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],
            ['username' => 'adminjur', 'full_name' => 'Admin Jurusan',
                'nidn' => '0000000012', 'role' => 'Admin Jurusan', 'unit' => $jur,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],
            ['username' => 'adminprodi', 'full_name' => 'Admin Program Studi',
                'nidn' => '0000000013', 'role' => 'Admin Program Studi', 'unit' => $prodi,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],

            // Tim Kurikulum (ditetapkan ke unit prodi sebagai default)
            ['username' => 'timkur', 'full_name' => 'Tim Kurikulum Prodi',
                'nidn' => '0000000020', 'role' => 'Tim Kurikulum', 'unit' => $prodi,
                'pimpinan' => false, 'tim_kur' => true, 'jabatan' => 'Anggota Tim Kurikulum'],

            // Koordinator Mata Kuliah
            ['username' => 'korma', 'full_name' => 'Koordinator Mata Kuliah',
                'nidn' => '0000000030', 'role' => 'Koordinator Mata Kuliah', 'unit' => $prodi,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Koordinator MK'],

            // Dosen
            ['username' => 'dosen', 'full_name' => 'Dosen Pengampu',
                'nidn' => '0000000040', 'role' => 'Dosen Pengampu', 'unit' => $prodi,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Dosen'],

            // Auditor
            ['username' => 'auditor', 'full_name' => 'Auditor Mutu',
                'nidn' => '0000000099', 'role' => 'Auditor Mutu', 'unit' => null,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Auditor'],
        ];

        foreach ($accounts as $a) {
            $user = User::firstOrCreate(
                ['username' => $a['username']],
                [
                    'id' => (string) Str::uuid(),
                    'email' => $a['username'].'@silogy.test',
                    'nidn' => $a['nidn'],
                    'full_name' => $a['full_name'],
                    'password' => Hash::make('Silogy2026!'),
                ]
            );

            $user->syncRoles([$a['role']]);

            if (! empty($a['unit'])) {
                AcademicUnitUser::updateOrCreate(
                    [
                        'academic_unit_id' => $a['unit']->id,
                        'user_id' => $user->id,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'status_pimpinan' => $a['pimpinan'],
                        'status_tim_kurikulum' => $a['tim_kur'],
                        'jabatan' => $a['jabatan'],
                    ]
                );
            }
        }
    }
}
