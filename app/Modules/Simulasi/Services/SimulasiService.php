<?php

namespace App\Modules\Simulasi\Services;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Simulasi\DataObjects\HasilPembangunan;
use App\Modules\Simulasi\DataObjects\HasilPembongkaran;
use App\Modules\Simulasi\DataObjects\StatusSimulasi;
use App\Modules\Simulasi\Exceptions\SimulasiSudahAdaException;
use App\Modules\Simulasi\Exceptions\SimulasiTidakAdaException;
use App\Modules\Simulasi\Models\SimulasiArtefak;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Support\PencatatArtefak;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Muka tunggal siklus hidup simulasi, dipakai halaman Filament maupun perintah
 * artisan supaya keduanya tidak pernah berbeda perilaku.
 */
class SimulasiService
{
    public function __construct(
        protected PembangunSimulasi $pembangun,
        protected PembongkarSimulasi $pembongkar,
        protected PencatatArtefak $pencatat,
    ) {}

    /**
     * Jalan simulasi yang artefaknya masih ada — termasuk yang gagal di tengah,
     * karena justru itu yang perlu dibersihkan.
     */
    public function aktif(): ?SimulasiJalan
    {
        return SimulasiJalan::query()
            ->whereIn('status', [
                SimulasiJalan::STATUS_BERJALAN,
                SimulasiJalan::STATUS_SELESAI,
                SimulasiJalan::STATUS_GAGAL,
            ])
            ->latest('mulai_pada')
            ->first();
    }

    public function status(): StatusSimulasi
    {
        $jalan = $this->aktif();

        if ($jalan === null) {
            return new StatusSimulasi(ada: false);
        }

        $cacah = [];

        // Query builder, bukan Eloquent: hasil agregat tidak punya properti
        // model sehingga analisis statis mempersoalkannya.
        $baris = DB::table('simulasi_artefak')
            ->where('simulasi_jalan_id', $jalan->getKey())
            ->groupBy('model_type')
            ->orderBy('model_type')
            ->pluck(DB::raw('count(*)'), 'model_type');

        foreach ($baris as $kelas => $jumlah) {
            $cacah[class_basename((string) $kelas)] = (int) $jumlah;
        }

        return new StatusSimulasi(
            ada: true,
            jalan: $jalan,
            cacah: $cacah,
            turunan: $this->cacahTurunan($jalan),
            takDikenal: $jalan->peringatan['tak_dikenal'] ?? [],
        );
    }

    /**
     * Baris yang tidak tercatat di buku besar tetapi pasti ikut terbuang lewat
     * CASCADE. Dihitung dari akar yang tercatat, bukan dari pola kode, supaya
     * angkanya tetap jujur tanpa membengkakkan buku besar dengan ribuan baris
     * yang toh dihapus mesin basis data dalam satu langkah.
     *
     * @return array<string, int>
     */
    public function cacahTurunan(SimulasiJalan $jalan): array
    {
        $idKelas = SimulasiArtefak::query()
            ->where('simulasi_jalan_id', $jalan->getKey())
            ->where('model_type', KelasMk::class)
            ->pluck('model_uuid');

        if ($idKelas->isEmpty()) {
            return [];
        }

        $idPeserta = KelasMkMahasiswa::query()
            ->whereIn('kelas_mk_id', $idKelas)
            ->pluck('id');

        return [
            'Peserta kelas' => $idPeserta->count(),
            'Nilai mahasiswa' => $idPeserta->isEmpty() ? 0 : NilaiMahasiswa::query()
                ->whereIn('kelas_mk_mahasiswa_id', $idPeserta)
                ->count(),
        ];
    }

    /**
     * @param  callable(string): void|null  $lapor
     */
    public function buat(?User $pemicu = null, ?callable $lapor = null): HasilPembangunan
    {
        if ($this->aktif() !== null) {
            throw SimulasiSudahAdaException::buat();
        }

        $mulai = microtime(true);

        // Baris jalan disimpan LEBIH DULU dan di luar transaksi pembangunan:
        // bila pembangunan mati di tengah, artefak yang terlanjur lahir tetap
        // tercatat sehingga masih bisa dibongkar lewat "Hapus Simulasi".
        $jalan = SimulasiJalan::query()->create([
            'status' => SimulasiJalan::STATUS_BERJALAN,
            'dipicu_oleh_id' => $pemicu?->getKey(),
            'mulai_pada' => now(),
        ]);

        try {
            // Jejak aktivitas dibungkam selama pembangunan: puluhan ribu baris
            // activity_log untuk data yang memang akan dibuang hanya mengubur
            // jejak audit yang sesungguhnya. Satu baris untuk jalan ini sudah
            // cukup memberi tahu auditor siapa menekan tombolnya dan kapan.
            activity()->withoutLogs(function () use ($jalan, $lapor): void {
                $this->pencatat->rekam($jalan, fn () => $this->pembangun->bangun($jalan, $lapor));
            });
        } catch (Throwable $galat) {
            $jalan->forceFill([
                'status' => SimulasiJalan::STATUS_GAGAL,
                'selesai_pada' => now(),
                'peringatan' => ['galat' => $galat->getMessage()],
            ])->save();

            throw $galat;
        }

        $cacah = $this->status()->cacah;
        $takDikenal = $this->pencatat->takDikenal();

        $jalan->forceFill([
            'status' => SimulasiJalan::STATUS_SELESAI,
            'selesai_pada' => now(),
            'ringkasan' => ['cacah' => $cacah],
            'peringatan' => $takDikenal === [] ? null : ['tak_dikenal' => $takDikenal],
        ])->save();

        return new HasilPembangunan(
            jalan: $jalan->refresh(),
            cacah: $cacah,
            durasiDetik: microtime(true) - $mulai,
            takDikenal: $takDikenal,
        );
    }

    public function hapus(?SimulasiJalan $jalan = null, bool $terapkan = true): HasilPembongkaran
    {
        $jalan ??= $this->aktif();

        if ($jalan === null) {
            throw SimulasiTidakAdaException::buat();
        }

        return $this->pembongkar->bongkar($jalan, $terapkan);
    }

    /**
     * @param  callable(string): void|null  $lapor
     */
    public function bangunUlang(?User $pemicu = null, ?callable $lapor = null): HasilPembangunan
    {
        // Sakelar coba-peran ikut dibawa: membangun ulang adalah menyegarkan
        // data latihan, bukan menutup pintunya. Menyalakan dari nol tetap
        // dimulai dari posisi mati.
        $sebelumnya = $this->aktif();
        $cobaPeran = $sebelumnya !== null && $sebelumnya->coba_peran;

        if ($sebelumnya !== null) {
            $this->hapus();
        }

        $hasil = $this->buat($pemicu, $lapor);

        if ($cobaPeran) {
            $this->aturCobaPeran(true);
        }

        return $hasil;
    }

    /**
     * Apakah tombol "Coba sebagai ‹peran›" sedang terbuka.
     *
     * Dua syarat, dan keduanya harus benar: instans ini mengizinkannya sama
     * sekali (pemutus keras di config/.env), DAN Super Admin menyalakannya
     * pada jalan simulasi yang sedang aktif.
     *
     * Karena sakelarnya menempel pada jalan simulasi, menghapus data simulasi
     * otomatis menutup jalur ini — tidak ada sakelar yatim yang tertinggal
     * menyala tanpa ada yang menyadarinya.
     */
    public function cobaPeranTerbuka(): bool
    {
        $jalan = $this->aktif();

        return (bool) config('simulasi.izinkan_coba_peran', true)
            && $jalan !== null
            && $jalan->coba_peran;
    }

    /**
     * Instans ini sama sekali melarang mode latihan, apa pun yang ditekan
     * Super Admin. Dipakai antarmuka untuk menjelaskan kenapa sakelarnya mati.
     */
    public function cobaPeranDilarangInstans(): bool
    {
        return ! (bool) config('simulasi.izinkan_coba_peran', true);
    }

    public function aturCobaPeran(bool $nyala): void
    {
        $jalan = $this->aktif();

        if ($jalan === null) {
            throw SimulasiTidakAdaException::buat();
        }

        $jalan->forceFill(['coba_peran' => $nyala])->save();
    }

    /**
     * Gerbang tunggal untuk tombol "Coba sebagai ‹peran›".
     *
     * Hanya baris yang benar-benar DIBUAT oleh jalan ini yang dianggap milik
     * simulasi. Sebuah akun nyata yang kebetulan bernama sama tidak akan pernah
     * lolos, karena ia tidak punya artefak.
     */
    public function memiliki(SimulasiJalan $jalan, Model $model): bool
    {
        return SimulasiArtefak::query()
            ->where('simulasi_jalan_id', $jalan->getKey())
            ->where('model_type', $model->getMorphClass())
            ->where('model_uuid', (string) $model->getKey())
            ->exists();
    }
}
