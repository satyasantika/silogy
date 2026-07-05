<?php

namespace App\Modules\Auth\Support;

class DomainPermissionLabels
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            // Institusi
            'kelola_unit' => 'Kelola unit akademik (scope dari penugasan)',
            'kelola_semester' => 'Kelola semester',
            'kelola_evaluasi' => 'Kelola evaluasi',

            // Admin & Auth
            'kelola_user' => 'Kelola pengguna (global)',
            'kelola_role' => 'Kelola role',
            'kelola_permission' => 'Kelola permission',
            'lihat_audit_log' => 'Lihat audit log',
            'konfigurasi_sistem' => 'Konfigurasi sistem',
            'impersonate_user' => 'Impersonate pengguna',

            // User-management per tipe unit

            // Kurikulum
            'kelola_kurikulum' => 'Kelola kurikulum',
            'kelola_profil_lulusan' => 'Kelola profil lulusan',
            'kelola_cpl' => 'Kelola CPL',
            'kelola_bok' => 'Kelola BoK',
            'kelola_mk' => 'Kelola mata kuliah',
            'kelola_mk_unit' => 'Kelola penawaran MK per unit',

            // CPMK / SubCPMK / Komponen
            'kelola_cpmk' => 'Kelola CPMK',
            'kelola_subcpmk' => 'Kelola SubCPMK',
            'kelola_komponen_penilaian' => 'Kelola komponen penilaian',

            // Kelas & Penilaian
            'kelola_kelas' => 'Kelola kelas MK',
            'setdosen_mk' => 'Penetapan dosen MK',
            'input_nilai' => 'Input nilai',
            'import_nilai' => 'Import nilai',

            // Laporan & AI
            'lihat_laporan' => 'Lihat laporan',
            'ekspor_data' => 'Ekspor data',
            'minta_analisis_ai' => 'Minta analisis AI',
            'lihat_dashboard' => 'Lihat dashboard',
        ];
    }

    public static function label(string $permission): string
    {
        return self::all()[$permission] ?? str_replace('_', ' ', ucfirst($permission));
    }
}
