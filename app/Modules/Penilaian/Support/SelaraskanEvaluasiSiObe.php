<?php

namespace App\Modules\Penilaian\Support;

use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use Illuminate\Support\Facades\DB;

/**
 * Menyamakan master evaluasi (jenis komponen penilaian) dengan komponen
 * SI-OBE: Aktivitas Partisipatif (Individu/Kelompok), Hasil
 * PjBL/PBL/IBL/CBL (Individu/Kelompok), Tugas, Kuis, UTS, UAS.
 *
 * Kode internal sengaja dipertahankan (hanya nama & urutan yang berubah)
 * agar asesmen, nilai, dan pengelompokan kategori yang sudah ada tidak
 * putus. "Studi Kasus" tidak punya padanan sendiri di SI-OBE — CBL sudah
 * mencakupnya — sehingga asesmennya dilebur ke Hasil PjBL/PBL/IBL/CBL
 * (Individu), keputusan pemilik produk 8 Nov 2026.
 */
final class SelaraskanEvaluasiSiObe
{
    /**
     * Urutan array = urutan tampil SI-OBE.
     *
     * @var array<string, array{nama: string, kategori: string, workcloud: string}>
     */
    public const DATA = [
        'partisipasi_individu' => ['nama' => 'Aktivitas Partisipatif (Individu)', 'kategori' => 'partisipasi', 'workcloud' => 'formatif'],
        'partisipasi_kelompok' => ['nama' => 'Aktivitas Partisipatif (Kelompok)', 'kategori' => 'partisipasi', 'workcloud' => 'formatif'],
        'proyek_individu' => ['nama' => 'Hasil PjBL/PBL/IBL/CBL (Individu)', 'kategori' => 'proyek', 'workcloud' => 'autentik'],
        'proyek_kelompok' => ['nama' => 'Hasil PjBL/PBL/IBL/CBL (Kelompok)', 'kategori' => 'proyek', 'workcloud' => 'autentik'],
        'tugas' => ['nama' => 'Tugas', 'kategori' => 'tugas', 'workcloud' => 'formatif'],
        'quiz' => ['nama' => 'Kuis', 'kategori' => 'tugas', 'workcloud' => 'formatif'],
        'uts' => ['nama' => 'UTS', 'kategori' => 'ujian', 'workcloud' => 'sumatif'],
        'uas' => ['nama' => 'UAS', 'kategori' => 'ujian', 'workcloud' => 'sumatif'],
    ];

    /**
     * Nama/kode lama (huruf kecil) → kode baru, supaya berkas impor lama
     * yang masih menulis nama lama tetap terbaca.
     *
     * @var array<string, string>
     */
    public const ALIAS = [
        'quiz' => 'quiz',
        'ujian tengah semester' => 'uts',
        'ujian akhir semester' => 'uas',
        'proyek individu' => 'proyek_individu',
        'proyek kelompok' => 'proyek_kelompok',
        'partisipasi individu' => 'partisipasi_individu',
        'partisipasi kelompok' => 'partisipasi_kelompok',
        'studi kasus' => 'proyek_individu',
        'studi_kasus' => 'proyek_individu',
    ];

    public const KODE_DILEBUR = 'studi_kasus';

    public const KODE_TUJUAN_LEBUR = 'proyek_individu';

    /**
     * Idempoten; tidak membuat baris baru (itu tugas EvaluasiSeeder) —
     * hanya menyelaraskan baris yang sudah ada.
     */
    public static function jalankan(): void
    {
        DB::transaction(function (): void {
            $dilebur = Evaluasi::query()->where('kode', self::KODE_DILEBUR)->first();
            $tujuan = Evaluasi::query()->where('kode', self::KODE_TUJUAN_LEBUR)->first();

            if ($dilebur !== null && $tujuan !== null) {
                KomponenPenilaian::query()
                    ->where('evaluasi_id', $dilebur->id)
                    ->update(['evaluasi_id' => $tujuan->id]);

                $dilebur->delete();
            }

            $urutan = 1;

            foreach (self::DATA as $kode => $atribut) {
                Evaluasi::query()
                    ->where('kode', $kode)
                    ->update([...$atribut, 'urutan' => $urutan++]);
            }
        });
    }
}
