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
            // Cakupan unit ditentukan penugasan (pivot academic_unit_users),
            // bukan permission per tipe unit.
            'kelola_unit',
            'kelola_semester', 'kelola_evaluasi',

            // ---- Admin & Auth ----
            'kelola_user', 'kelola_role', 'kelola_permission', 'impersonate_user',
            'lihat_audit_log', 'konfigurasi_sistem',

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
            'kelola_unit',
            'kelola_semester', 'kelola_evaluasi',
            'kelola_user', 'kelola_role', 'kelola_permission', 'impersonate_user',
            'lihat_audit_log', 'konfigurasi_sistem',
        ]);

        // --- Admin (generik; level kerja mengikuti penugasan unit) ---
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->syncPermissions([
            'kelola_unit',
            // Delegasi domain akademik (scope tetap dari penugasan unit):
            'kelola_kurikulum', 'kelola_profil_lulusan',
            'kelola_cpl', 'kelola_bok', 'kelola_mk', 'kelola_mk_unit',
            'kelola_cpmk', 'kelola_subcpmk', 'kelola_komponen_penilaian',
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
            'kelola_kelas', // kelas MK prodi (penugasan prodi)
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

        // --- Pimpinan (generik; level & jabatan dari penugasan unit) ---
        $pimpinan = Role::firstOrCreate(['name' => 'Pimpinan', 'guard_name' => 'web']);
        $pimpinan->syncPermissions([
            'lihat_laporan', 'ekspor_data', 'minta_analisis_ai', 'lihat_dashboard',
        ]);

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
                'nidn' => '0000000001', 'role' => 'Pimpinan', 'unit' => $univ,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Rektor'],
            ['username' => 'wakilrektor', 'full_name' => 'Wakil Rektor I',
                'nidn' => '0000000002', 'role' => 'Pimpinan', 'unit' => $univ,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Wakil Rektor I'],

            // Pimpinan Fakultas
            ['username' => 'dekan', 'full_name' => 'Dekan',
                'nidn' => '0000000003', 'role' => 'Pimpinan', 'unit' => $fak,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Dekan'],
            ['username' => 'wakildekan', 'full_name' => 'Wakil Dekan I',
                'nidn' => '0000000004', 'role' => 'Pimpinan', 'unit' => $fak,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Wakil Dekan I'],

            // Pimpinan Jurusan
            ['username' => 'kajur', 'full_name' => 'Ketua Jurusan',
                'nidn' => '0000000005', 'role' => 'Pimpinan', 'unit' => $jur,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Ketua Jurusan'],
            ['username' => 'sekjur', 'full_name' => 'Sekretaris Jurusan',
                'nidn' => '0000000006', 'role' => 'Pimpinan', 'unit' => $jur,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Sekretaris Jurusan'],

            // Pimpinan Prodi
            ['username' => 'kaprodi', 'full_name' => 'Ketua Program Studi',
                'nidn' => '0000000007', 'role' => 'Pimpinan', 'unit' => $prodi,
                'pimpinan' => true, 'tim_kur' => false, 'jabatan' => 'Ketua Program Studi'],

            // Admin per unit
            ['username' => 'adminuniv', 'full_name' => 'Admin Universitas',
                'nidn' => '0000000010', 'role' => 'Admin', 'unit' => $univ,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],
            ['username' => 'adminfak', 'full_name' => 'Admin Fakultas',
                'nidn' => '0000000011', 'role' => 'Admin', 'unit' => $fak,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],
            ['username' => 'adminjur', 'full_name' => 'Admin Jurusan',
                'nidn' => '0000000012', 'role' => 'Admin', 'unit' => $jur,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],
            ['username' => 'adminprodi', 'full_name' => 'Admin Program Studi',
                'nidn' => '0000000013', 'role' => 'Admin', 'unit' => $prodi,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Admin'],

            // Tim Kurikulum per level unit (terpisah)
            ['username' => 'timkur', 'full_name' => 'Tim Kurikulum Prodi',
                'nidn' => '0000000020', 'role' => 'Tim Kurikulum', 'unit' => $prodi,
                'pimpinan' => false, 'tim_kur' => true, 'jabatan' => 'Anggota Tim Kurikulum'],
            ['username' => 'timkurfak', 'full_name' => 'Tim Kurikulum Fakultas',
                'nidn' => '0000000021', 'role' => 'Tim Kurikulum', 'unit' => $fak,
                'pimpinan' => false, 'tim_kur' => true, 'jabatan' => 'Anggota Tim Kurikulum'],
            ['username' => 'timkuruniv', 'full_name' => 'Tim Kurikulum Universitas',
                'nidn' => '0000000022', 'role' => 'Tim Kurikulum', 'unit' => $univ,
                'pimpinan' => false, 'tim_kur' => true, 'jabatan' => 'Anggota Tim Kurikulum'],

            // Dosen merangkap tim kurikulum di tiga level (demo role switcher)
            ['username' => 'dosentimkur', 'full_name' => 'Dosen merangkap Tim Kurikulum',
                'nidn' => '0000000023', 'role' => 'Dosen Pengampu', 'unit' => $prodi,
                'pimpinan' => false, 'tim_kur' => true, 'jabatan' => 'Dosen & Tim Kurikulum'],

            // Koordinator Mata Kuliah
            ['username' => 'korma', 'full_name' => 'Koordinator Mata Kuliah',
                'nidn' => '0000000030', 'role' => 'Koordinator Mata Kuliah', 'unit' => $prodi,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Koordinator MK'],

            // Dosen per level unit
            ['username' => 'dosenuniv', 'full_name' => 'Dosen Pengampu Universitas',
                'nidn' => '0000000041', 'role' => 'Dosen Pengampu', 'unit' => $univ,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Dosen'],
            ['username' => 'dosenfak', 'full_name' => 'Dosen Pengampu Fakultas',
                'nidn' => '0000000042', 'role' => 'Dosen Pengampu', 'unit' => $fak,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Dosen'],
            ['username' => 'dosenjur', 'full_name' => 'Dosen Pengampu Jurusan',
                'nidn' => '0000000043', 'role' => 'Dosen Pengampu', 'unit' => $jur,
                'pimpinan' => false, 'tim_kur' => false, 'jabatan' => 'Dosen'],
            ['username' => 'dosen', 'full_name' => 'Dosen Pengampu Program Studi',
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
                    'password' => Hash::make('siliwangi'),
                    'email_verified_at' => now(),
                ]
            );

            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            // Sinkronkan kata sandi demo walau akun sudah ada sebelumnya,
            // agar seluruh akun demo selalu bisa login dengan kata sandi baku.
            if (! Hash::check('siliwangi', $user->password)) {
                $user->forceFill(['password' => Hash::make('siliwangi')])->save();
            }

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

        // =========================================================
        // 4. MIGRASI ROLE LAMA -> ROLE GENERIK
        // =========================================================
        // Pemetaan role per level (Admin/Pimpinan Universitas..Prodi) ke role
        // generik; level kerja kini murni dari penugasan unit. Aman dijalankan
        // berulang pada database lama maupun baru.
        $pemetaanRoleLama = [
            'Admin Universitas' => 'Admin',
            'Admin Fakultas' => 'Admin',
            'Admin Jurusan' => 'Admin',
            'Admin Program Studi' => 'Admin',
            'Pimpinan Universitas' => 'Pimpinan',
            'Pimpinan Fakultas' => 'Pimpinan',
            'Pimpinan Jurusan' => 'Pimpinan',
            'Pimpinan Program Studi' => 'Pimpinan',
        ];

        foreach ($pemetaanRoleLama as $lama => $baru) {
            $roleLama = Role::where('name', $lama)->where('guard_name', 'web')->first();

            if (! $roleLama) {
                continue;
            }

            User::role($lama)->get()->each(function (User $user) use ($lama, $baru): void {
                $user->removeRole($lama);
                $user->assignRole($baru);
            });

            $roleLama->delete();
        }

        // Permission per tipe unit yang sudah digantikan kelola_unit.
        Permission::query()
            ->whereIn('name', [
                'kelola_universitas', 'kelola_fakultas', 'kelola_jurusan', 'kelola_prodi',
                'kelola_user_universitas', 'kelola_user_fakultas',
                'kelola_user_jurusan', 'kelola_user_prodi',
            ])
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        // dosentimkur: dosen sekaligus tim kurikulum pada TIGA level
        // (universitas, fakultas, prodi) — contoh pemakaian role switcher
        // dan pengelolaan kurikulum penanda lintas level oleh satu akun.
        $dosenTimkur = User::query()->where('username', 'dosentimkur')->first();

        if ($dosenTimkur) {
            $dosenTimkur->syncRoles(['Dosen Pengampu', 'Tim Kurikulum']);

            foreach (array_filter([$fak, $univ]) as $unitTimkur) {
                AcademicUnitUser::updateOrCreate(
                    [
                        'academic_unit_id' => $unitTimkur->id,
                        'user_id' => $dosenTimkur->id,
                    ],
                    [
                        'id' => (string) Str::uuid(),
                        'status_pimpinan' => false,
                        'status_tim_kurikulum' => true,
                        'jabatan' => 'Dosen & Tim Kurikulum',
                    ]
                );
            }
        }
    }
}
