<?php

namespace Database\Seeders;

use App\Modules\Kalender\Models\Semester;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SemesterSeeder extends Seeder
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function semesterData(): array
    {
        return [
            [
                'kode' => '20241',
                'nama' => 'Ganjil 2024/2025',
                'tahun_mulai' => 2024,
                'tahun_selesai' => 2025,
                'jenis' => 'ganjil',
                'tanggal_mulai' => '2024-08-01',
                'tanggal_selesai' => '2024-12-20',
            ],
            [
                'kode' => '20242',
                'nama' => 'Genap 2024/2025',
                'tahun_mulai' => 2024,
                'tahun_selesai' => 2025,
                'jenis' => 'genap',
                'tanggal_mulai' => '2025-02-01',
                'tanggal_selesai' => '2025-06-30',
            ],
            [
                'kode' => '20251',
                'nama' => 'Ganjil 2025/2026',
                'tahun_mulai' => 2025,
                'tahun_selesai' => 2026,
                'jenis' => 'ganjil',
                'tanggal_mulai' => '2025-08-01',
                'tanggal_selesai' => '2025-12-20',
            ],
            [
                'kode' => '20252',
                'nama' => 'Genap 2025/2026',
                'tahun_mulai' => 2025,
                'tahun_selesai' => 2026,
                'jenis' => 'genap',
                'tanggal_mulai' => '2026-02-01',
                'tanggal_selesai' => '2026-06-30',
            ],
            [
                'kode' => '20261',
                'nama' => 'Ganjil 2026/2027',
                'tahun_mulai' => 2026,
                'tahun_selesai' => 2027,
                'jenis' => 'ganjil',
                'tanggal_mulai' => '2026-08-01',
                'tanggal_selesai' => '2026-12-20',
            ],
            [
                'kode' => '20262',
                'nama' => 'Genap 2026/2027',
                'tahun_mulai' => 2026,
                'tahun_selesai' => 2027,
                'jenis' => 'genap',
                'tanggal_mulai' => '2027-02-01',
                'tanggal_selesai' => '2027-06-30',
            ],
            [
                'kode' => '20271',
                'nama' => 'Ganjil 2027/2028',
                'tahun_mulai' => 2027,
                'tahun_selesai' => 2028,
                'jenis' => 'ganjil',
                'tanggal_mulai' => '2027-08-01',
                'tanggal_selesai' => '2027-12-20',
            ],
            [
                'kode' => '20272',
                'nama' => 'Genap 2027/2028',
                'tahun_mulai' => 2027,
                'tahun_selesai' => 2028,
                'jenis' => 'genap',
                'tanggal_mulai' => '2028-02-01',
                'tanggal_selesai' => '2028-06-30',
            ],
        ];
    }

    public function run(): void
    {
        $hariIni = Carbon::today();
        $kodeAktif = null;

        foreach (self::semesterData() as $row) {
            $mulai = Carbon::parse($row['tanggal_mulai']);
            $selesai = Carbon::parse($row['tanggal_selesai']);
            $aktif = $hariIni->between($mulai, $selesai);

            if ($aktif) {
                $kodeAktif = $row['kode'];
            }

            Semester::query()->updateOrCreate(
                ['kode' => $row['kode']],
                [
                    ...$row,
                    'status_aktif' => $aktif,
                ],
            );
        }

        if ($kodeAktif === null) {
            $kodeAktif = '20251';
            Semester::query()->update(['status_aktif' => false]);
            Semester::query()->where('kode', $kodeAktif)->update(['status_aktif' => true]);
        }
    }
}
