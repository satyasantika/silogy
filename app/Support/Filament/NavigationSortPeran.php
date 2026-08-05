<?php

namespace App\Support\Filament;

use App\Models\User;
use App\Modules\Auth\Support\PeranUnitFormFields;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;

/**
 * Urutan menu khusus peran Tim Kurikulum (menunya sudah digabung rata
 * tanpa kategori, lihat [[NavigationGroupPeran]]), Koordinator Mata
 * Kuliah, dan Pimpinan (tiga menu laporan CPL). Urutan Tim Kurikulum
 * berbeda antara level PRODI dan level non-prodi (fakultas/universitas/
 * jurusan) — lihat URUTAN_TIM_KURIKULUM_PRODI vs
 * URUTAN_TIM_KURIKULUM_NON_PRODI di bawah. Urutan default per kategori
 * yang dipakai peran lain (Admin, dst.) TIDAK diubah oleh helper ini.
 */
final class NavigationSortPeran
{
    /**
     * "Analisis MK" (Prodi/Fakultas/Universitas — hanya satu yang pernah
     * tampil sekaligus per unit Tim Kurikulum, lihat canAccess() di
     * HasAnalisisMkForUnitType) selalu didorong paling akhir menu, di
     * kedua level unit — karena itu satu konstanta besar dipakai bersama
     * oleh kedua tabel di bawah.
     */
    private const SORT_ANALISIS_MK = 999;

    private const URUTAN_TIM_KURIKULUM_PRODI = [
        'kurikulum' => 10,
        'profil-lulusan' => 20,
        'cpl' => 30,
        'bok' => 40,
        'mata-kuliah' => 50,
        'penawaran-mk' => 60,
        'profil-cpl' => 70,
        'cpl-bok' => 80,
        'cpl-mk' => 90,
        // Tidak diminta eksplisit tapi tetap tampil (Tim Kurikulum punya
        // permission kelola_kelas) — didorong ke belakang urutan yang
        // diminta, bukan dibiarkan pakai $navigationSort default yang
        // lebih kecil dan menyelip di depan.
        'kelas-mk' => 100,
        'analisis-mk' => self::SORT_ANALISIS_MK,
    ];

    private const URUTAN_TIM_KURIKULUM_NON_PRODI = [
        'kurikulum' => 10,
        'cpl' => 20,
        'bok' => 30,
        'mata-kuliah' => 40,
        'cpl-bok' => 50,
        'cpl-mk' => 60,
        // Sama alasannya dengan tabel prodi di atas.
        'kelas-mk' => 70,
        'analisis-mk' => self::SORT_ANALISIS_MK,
    ];

    private const URUTAN_KOORDINATOR_MK = [
        'cpmk' => 10,
        'subcpmk' => 20,
        'komponen-penilaian' => 30,
        'cpl-cpmk' => 40,
        'subcpmk-asesmen' => 50,
        'laporan-koordinator' => 60,
        // Tidak diminta eksplisit tapi tetap tampil (Peserta Kelas) —
        // didorong ke belakang urutan yang diminta, bukan dibiarkan pakai
        // $navigationSort default yang lebih kecil dan menyelip di depan.
        'peserta-kelas' => 70,
    ];

    private const URUTAN_PIMPINAN = [
        'daftar-kurikulum' => 5,
        'hasil-analisis-cpl' => 10,
        'grafik-cpl' => 20,
        'analisis-cpl-mahasiswa' => 30,
    ];

    public static function resolve(string $item, ?int $default): ?int
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return $default;
        }

        $role = PeranUnitFormFields::defaultRole($user);

        if ($role === 'Tim Kurikulum') {
            $urutan = self::isUnitAktifProdi()
                ? self::URUTAN_TIM_KURIKULUM_PRODI
                : self::URUTAN_TIM_KURIKULUM_NON_PRODI;

            return $urutan[$item] ?? $default;
        }

        if ($role === 'Koordinator Mata Kuliah') {
            return self::URUTAN_KOORDINATOR_MK[$item] ?? $default;
        }

        if ($role === 'Pimpinan') {
            return self::URUTAN_PIMPINAN[$item] ?? $default;
        }

        return $default;
    }

    /**
     * Level unit dari kurikulum yang sedang dikerjakan — sumber yang sama
     * dipakai setiap halaman Interaksi/Analisis MK untuk canAccess()
     * (lihat KurikulumTerpilih::current()), termasuk fallback default()-nya
     * (unit terendah bila belum pernah memilih eksplisit lewat filter
     * kurikulum). TIDAK memakai PeranUnitFormFields::defaultUnitId(), yang
     * hanya terisi setelah pemilihan unit eksplisit di gerbang Pilih Peran
     * & Unit dan tetap null pada sesi baru — salah menganggap prodi sebagai
     * non-prodi selama itu.
     */
    private static function isUnitAktifProdi(): bool
    {
        return KurikulumTerpilih::current()?->academicUnit?->isProdi() ?? false;
    }
}
