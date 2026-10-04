<?php

namespace App\Modules\Simulasi\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Setelan ruang latihan yang diatur Super Admin dari menu Simulasi dan
 * disimpan di tabel simulasi_pengaturan. Sengaja tidak memakai .env: setelan
 * ini harus bisa diubah dari antarmuka tanpa akses ke server.
 */
final class PengaturanSimulasi
{
    /** @var array<string, array{bawaan: int, min: int, maks: int, label: string, satuan: string}> */
    public const DEFINISI = [
        'kapasitas' => ['bawaan' => 200, 'min' => 1, 'maks' => 1000, 'label' => 'Kapasitas ruang', 'satuan' => 'ruang'],
        'batas_coba' => ['bawaan' => 60, 'min' => 5, 'maks' => 1000, 'label' => 'Batas percobaan masuk per IP', 'satuan' => 'per menit'],
        'batas_token_salah' => ['bawaan' => 30, 'min' => 5, 'maks' => 600, 'label' => 'Batas token salah per IP', 'satuan' => 'per menit'],
        'umur_hari' => ['bawaan' => 3, 'min' => 1, 'maks' => 60, 'label' => 'Umur ruang tanpa aktivitas', 'satuan' => 'hari'],
        'sewa_menit' => ['bawaan' => 15, 'min' => 10, 'maks' => 240, 'label' => 'Peran dibebaskan setelah tak aktif', 'satuan' => 'menit'],
    ];

    private const AWALAN = 'ruang.';

    public static function ambil(string $kunci): int
    {
        $d = self::DEFINISI[$kunci];
        $nilai = DB::table('simulasi_pengaturan')->where('kunci', self::AWALAN.$kunci)->value('nilai');

        return $nilai === null ? $d['bawaan'] : max($d['min'], min($d['maks'], (int) $nilai));
    }

    public static function atur(string $kunci, int $nilai): void
    {
        $d = self::DEFINISI[$kunci];
        $nilai = max($d['min'], min($d['maks'], $nilai));

        DB::table('simulasi_pengaturan')->updateOrInsert(
            ['kunci' => self::AWALAN.$kunci],
            ['nilai' => (string) $nilai, 'updated_at' => now(), 'created_at' => now()],
        );

        Cache::forget('sim-atur:'.$kunci);
    }

    /**
     * Versi bercache 30 detik untuk jalur panas (pembatas laju per permintaan).
     * Gagal membaca (misalnya tabel belum ada) jatuh ke nilai bawaan.
     */
    public static function ambilCepat(string $kunci): int
    {
        return (int) Cache::remember('sim-atur:'.$kunci, 30, function () use ($kunci): int {
            try {
                return self::ambil($kunci);
            } catch (\Throwable) {
                return self::DEFINISI[$kunci]['bawaan'];
            }
        });
    }

    /** @return array<string, int> */
    public static function semua(): array
    {
        return collect(array_keys(self::DEFINISI))->mapWithKeys(fn (string $k): array => [$k => self::ambil($k)])->all();
    }
}
