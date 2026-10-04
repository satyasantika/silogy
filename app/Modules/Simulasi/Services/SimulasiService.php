<?php

namespace App\Modules\Simulasi\Services;

use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Simulasi\DataObjects\HasilPembangunan;
use App\Modules\Simulasi\DataObjects\HasilPembongkaran;
use App\Modules\Simulasi\DataObjects\StatusSimulasi;
use App\Modules\Simulasi\Exceptions\KapasitasSandboxPenuhException;
use App\Modules\Simulasi\Models\SimulasiArtefak;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Support\AkunSimulasi;
use App\Modules\Simulasi\Support\PencatatArtefak;
use App\Modules\Simulasi\Support\PengaturanSimulasi;
use App\Modules\Simulasi\Support\Ranah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Muka tunggal siklus hidup sandbox simulasi, dipakai halaman Filament,
 * perintah artisan, dan tombol "Coba sebagai" supaya semuanya berperilaku sama.
 *
 * Model kerjanya: satu pengunjung = satu sandbox (satu paket data utuh) yang
 * dibagi semua tab perannya. Sandbox disiapkan lebih dulu di kolam; bila
 * kolam kosong, sandbox dibangun saat diminta. Sandbox yang gagal dibangun
 * langsung dibongkar, jadi tidak pernah sampai ke pengunjung.
 */
class SimulasiService
{
    public const KUNCI_COBA_PERAN = 'coba_peran';

    public function __construct(
        protected PembangunSimulasi $pembangun,
        protected PembongkarSimulasi $pembongkar,
        protected PencatatArtefak $pencatat,
    ) {}

    // ── Contoh terisi (salinan bersama) ──────────────────────────────────

    /**
     * Contoh terisi bersama: SATU sandbox hanya-baca yang dilihat semua pengunjung.
     * Dibangun sekali (saat deploy, penjadwal, atau tombol Bangun Ulang) dan tidak
     * pernah dibangun di dalam permintaan pengunjung kecuali belum ada sama sekali.
     * Null bila belum ada dan tidak bisa dibangun (kapasitas penuh).
     */
    public function contohTerisi(): ?SimulasiJalan
    {
        $ada = $this->contohTerisiSiap();

        if ($ada !== null) {
            return $ada;
        }

        // Kunci menahan klik serentak supaya tidak lahir dua salinan bersama.
        return Cache::lock('sim-contoh-terisi', 300)->block(240, function (): ?SimulasiJalan {
            $ada = $this->contohTerisiSiap();

            if ($ada !== null) {
                return $ada;
            }

            // Sudah ada yang sedang dibangun (tombol Siapkan atau penjadwal):
            // jangan bangun kembar, biarkan pengunjung mencoba lagi sebentar lagi.
            if ($this->contohTerisiSedangDibangun()) {
                return null;
            }

            try {
                return $this->buat(mode: SimulasiJalan::MODE_TERISI, bersama: true)->jalan;
            } catch (KapasitasSandboxPenuhException) {
                return null;
            }
        });
    }

    public function contohTerisiSedangDibangun(): bool
    {
        return SimulasiJalan::query()
            ->bersama()
            ->where('status', SimulasiJalan::STATUS_BERJALAN)
            ->where('mulai_pada', '>', now()->subMinutes(30))
            ->exists();
    }

    /** Salinan bersama terbaru yang utuh, atau null. */
    public function contohTerisiSiap(): ?SimulasiJalan
    {
        $jalan = SimulasiJalan::query()
            ->bersama()
            ->where('status', SimulasiJalan::STATUS_SELESAI)
            ->orderByDesc('selesai_pada')
            ->first();

        return $jalan !== null && $this->akunLengkap($jalan) ? $jalan : null;
    }

    /**
     * Membangun salinan bersama yang baru. Yang lama tetap melayani sampai yang
     * baru selesai lalu dibuang oleh selesaikan(), jadi pengunjung tidak pernah
     * melihat contoh yang hilang.
     */
    public function bangunUlangContohTerisi(?callable $lapor = null): SimulasiJalan
    {
        return Cache::lock('sim-contoh-terisi', 300)->block(240, function () use ($lapor): SimulasiJalan {
            return $this->buat(lapor: $lapor, mode: SimulasiJalan::MODE_TERISI, bersama: true)->jalan;
        });
    }

    /** Membuang salinan bersama yang lebih lama setelah penggantinya utuh. */
    private function buangBersamaLama(SimulasiJalan $baru): void
    {
        $lama = SimulasiJalan::query()
            ->bersama()
            ->masihAda()
            ->whereKeyNot($baru->getKey())
            ->where('status', '!=', SimulasiJalan::STATUS_BERJALAN)
            ->get();

        foreach ($lama as $usang) {
            try {
                $this->hapus($usang);
            } catch (Throwable) {
                // Tersapu penjadwal bila gagal; salinan baru sudah melayani.
            }
        }
    }

    /** Salinan bersama dibangun dengan bentuk lama (bukan VERSI_CONTOH saat ini). */
    public function contohTerisiUsang(): bool
    {
        $ada = $this->contohTerisiSiap();

        return $ada !== null && (int) ($ada->ringkasan['versi'] ?? 0) < PembangunSimulasi::VERSI_CONTOH;
    }

    /**
     * Pastikan contoh terisi ada dan berbentuk terbaru. Dipanggil deploy dan
     * penjadwal; pengunjung tidak pernah menunggu pembangunannya.
     */
    public function rawatContohTerisi(): string
    {
        if ($this->contohTerisiSedangDibangun()) {
            return 'sedang dibangun';
        }

        if ($this->contohTerisiSiap() === null) {
            return $this->contohTerisi() !== null ? 'dibangun' : 'gagal';
        }

        if ($this->contohTerisiUsang()) {
            try {
                $this->bangunUlangContohTerisi();

                return 'dibangun ulang';
            } catch (Throwable) {
                return 'gagal diperbarui';
            }
        }

        return 'ada';
    }

    /**
     * Membuang ruang yang tak dipakai melebihi umur yang diatur Super Admin,
     * ruang gagal/macet, dan baris riwayat lama. Contoh terisi bersama tidak
     * pernah ikut dibuang.
     *
     * @return int jumlah ruang yang dibongkar
     */
    public function bersihkanKedaluwarsa(): int
    {
        $batas = now()->subDays(PengaturanSimulasi::ambil('umur_hari'));

        $usang = SimulasiJalan::query()->masihAda()
            ->where('bersama', false)
            ->where(function ($q) use ($batas): void {
                $q->where('status', SimulasiJalan::STATUS_GAGAL)
                    ->orWhere(fn ($q) => $q->where('status', SimulasiJalan::STATUS_BERJALAN)
                        ->where('mulai_pada', '<', now()->subMinutes(30)))
                    ->orWhere(fn ($q) => $q->where('status', SimulasiJalan::STATUS_SELESAI)
                        ->whereRaw('coalesce(terakhir_aktif_pada, selesai_pada, mulai_pada) < ?', [$batas]));
            })
            ->get();

        foreach ($usang as $jalan) {
            $this->hapus($jalan);
        }

        $warisan = $this->bongkarWarisan();

        SimulasiJalan::query()
            ->where('status', SimulasiJalan::STATUS_DIBONGKAR)
            ->where('dibongkar_pada', '<', now()->subDays(7))
            ->delete();

        return $usang->count() + $warisan;
    }

    /**
     * Membongkar sandbox peninggalan versi sebelum ruang bertoken: sandbox per
     * pengunjung, cadangan kolam, dan ruang kosong tanpa token. Ciri pastinya:
     * bukan salinan bersama, tak bertoken, dan dibangun sebelum penanda versi
     * contoh (VERSI_CONTOH) dicatat. Sandbox yang masih berjalan tidak disentuh.
     * Hanya baris yang tercatat di buku besar simulasi_artefak yang terbuang,
     * jadi data inti tidak terjangkau.
     *
     * @return int jumlah sandbox warisan yang dibongkar
     */
    public function bongkarWarisan(): int
    {
        $warisan = SimulasiJalan::query()->masihAda()
            ->where('bersama', false)
            ->whereNull('pin')
            ->where('status', '!=', SimulasiJalan::STATUS_BERJALAN)
            ->get()
            ->filter(fn (SimulasiJalan $j): bool => (int) ($j->ringkasan['versi'] ?? 0) < PembangunSimulasi::VERSI_CONTOH);

        foreach ($warisan as $jalan) {
            try {
                $this->hapus($jalan);
            } catch (Throwable) {
                // Dicoba lagi pada penyapuan berikutnya.
            }
        }

        return $warisan->count();
    }

    // ── Pembacaan ────────────────────────────────────────────────────────

    /**
     * @return Collection<int, SimulasiJalan>
     */
    public function daftar(): Collection
    {
        return SimulasiJalan::query()->masihAda()->orderByDesc('mulai_pada')->get();
    }

    /**
     * Cacah artefak seluruh sandbox sekaligus (satu query).
     *
     * @return array<string, int> id sandbox => jumlah baris
     */
    public function totalArtefak(): array
    {
        return DB::table('simulasi_artefak')
            ->select('simulasi_jalan_id', DB::raw('count(*) as jumlah'))
            ->groupBy('simulasi_jalan_id')
            ->pluck('jumlah', 'simulasi_jalan_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    public function status(SimulasiJalan $jalan): StatusSimulasi
    {
        $cacah = [];

        // Query builder, bukan Eloquent: hasil agregat tidak punya properti model.
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
     * CASCADE, dihitung dari akar yang tercatat.
     *
     * @return array<string, int>
     */
    public function cacahTurunan(SimulasiJalan $jalan): array
    {
        $idKelas = SimulasiArtefak::query()
            ->where('simulasi_jalan_id', $jalan->getKey())
            ->where('model_type', 'App\Modules\Kelas\Models\KelasMk')
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

    // ── Pembangunan & pembongkaran ───────────────────────────────────────

    public function buat(
        ?User $pemicu = null,
        ?callable $lapor = null,
        string $mode = SimulasiJalan::MODE_TERISI,
        bool $bersama = false,
    ): HasilPembangunan {
        $jalan = $this->mulai($pemicu, $mode, $bersama);

        return $this->selesaikan($jalan, $lapor);
    }

    /**
     * Mendaftarkan satu ruang (baris berstatus berjalan). Ruang latihan (kosong)
     * mendapat token; contoh terisi bersama tidak.
     */
    public function mulai(
        ?User $pemicu = null,
        string $mode = SimulasiJalan::MODE_TERISI,
        bool $bersama = false,
    ): SimulasiJalan {
        $mode = $this->mode($mode);
        // Hanya contoh terisi yang dibagi; ruang latihan selalu punya token sendiri.
        $bersama = $bersama && $mode === SimulasiJalan::MODE_TERISI;

        // Kapasitas hanya menghitung ruang latihan; contoh terisi bersama tidak makan jatah.
        if (! $bersama) {
            $maks = PengaturanSimulasi::ambil('kapasitas');

            if ($this->jumlahRuang() >= $maks) {
                throw KapasitasSandboxPenuhException::buat($maks);
            }
        }

        // Baris jalan disimpan LEBIH DULU dan di luar transaksi pembangunan:
        // artefak yang terlanjur lahir tetap tercatat sehingga bisa dibongkar.
        $jalan = SimulasiJalan::query()->create([
            'status' => SimulasiJalan::STATUS_BERJALAN,
            'dipicu_oleh_id' => $pemicu?->getKey(),
            'mode' => $mode,
            'bersama' => $bersama,
            'pin' => $bersama || $mode === SimulasiJalan::MODE_TERISI ? null : $this->buatPin(),
            'jumlah_mk' => $mode === SimulasiJalan::MODE_KOSONG ? 0 : 1,
            'mulai_pada' => now(),
        ]);

        Cache::put($this->kunciProgres($jalan), ['tahap' => [], 'terakhir' => microtime(true)], now()->addHours(2));

        return $jalan;
    }

    /** Mengganti token ruang (bila bocor). Pemegang peran yang sudah masuk tidak terganggu. */
    public function gantiPin(SimulasiJalan $ruang): string
    {
        $baru = $this->buatPin();
        $ruang->forceFill(['pin' => $baru])->save();

        return $baru;
    }

    /** Jumlah ruang latihan yang masih hidup (tanpa contoh terisi bersama). */
    public function jumlahRuang(): int
    {
        return SimulasiJalan::query()->masihAda()->where('bersama', false)->count();
    }

    /** Token ruang: 6 karakter tanpa huruf/angka yang mudah tertukar (I, L, O, 0, 1). */
    public function buatPin(): string
    {
        $huruf = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

        for ($i = 0; $i < 20; $i++) {
            $pin = '';
            for ($j = 0; $j < 6; $j++) {
                $pin .= $huruf[random_int(0, strlen($huruf) - 1)];
            }

            if (! SimulasiJalan::query()->where('pin', $pin)->exists()) {
                return $pin;
            }
        }

        throw new \RuntimeException('Tidak berhasil membuat token ruang yang unik.');
    }

    public static function normalisasiPin(string $masukan): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $masukan) ?? '');
    }

    /**
     * Tahap 2 pembangunan: mengisi sandbox yang sudah didaftarkan mulai().
     * Melempar ulang galat setelah sandbox setengah jadi dibongkar.
     *
     * @param  callable(string): void|null  $lapor
     */
    public function selesaikan(SimulasiJalan $jalan, ?callable $lapor = null): HasilPembangunan
    {
        @set_time_limit(0);
        ignore_user_abort(true);
        $mulai = microtime(true);

        $pelapor = function (string $langkah) use ($jalan, $lapor): void {
            $this->catatTahap($jalan, $langkah);

            if ($lapor !== null) {
                $lapor($langkah);
            }
        };

        try {
            // Jejak aktivitas dibungkam: puluhan ribu baris activity_log untuk
            // data yang memang akan dibuang hanya mengubur jejak audit asli.
            activity()->withoutLogs(function () use ($jalan, $pelapor): void {
                $this->pencatat->rekam($jalan, fn () => $this->pembangun->bangun($jalan, $pelapor));
            });
        } catch (Throwable $galat) {
            $this->catatGagal($jalan, $galat->getMessage());

            $jalan->forceFill([
                'status' => SimulasiJalan::STATUS_GAGAL,
                'selesai_pada' => now(),
                'peringatan' => ['galat' => $galat->getMessage()],
            ])->save();

            // Dibuang seketika: sandbox setengah jadi tidak boleh dilihat siapa pun.
            // Bila pembongkaran pun gagal, barisnya tetap berstatus gagal dan
            // disapu oleh bersihkanKedaluwarsa().
            try {
                $this->hapus($jalan->fresh());
            } catch (Throwable) {
            }

            throw $galat;
        }

        $this->tutupTahapTerbuka($jalan);

        $cacah = $this->status($jalan)->cacah;
        $takDikenal = $this->pencatat->takDikenal();

        $jalan->forceFill([
            'status' => SimulasiJalan::STATUS_SELESAI,
            'selesai_pada' => now(),
            'ringkasan' => ['cacah' => $cacah, 'versi' => PembangunSimulasi::VERSI_CONTOH],
            'peringatan' => $takDikenal === [] ? null : ['tak_dikenal' => $takDikenal],
        ])->save();

        if ($jalan->bersama) {
            $this->buangBersamaLama($jalan);
        }

        return new HasilPembangunan(
            jalan: $jalan->refresh(),
            cacah: $cacah,
            durasiDetik: microtime(true) - $mulai,
            takDikenal: $takDikenal,
        );
    }

    /**
     * Menjalankan pembangunan beberapa ruang yang sudah didaftarkan, berurutan
     * dalam SATU proses latar belakang (supaya tidak menyaingi pengunjung).
     * Mengembalikan true bila dijalankan di latar belakang, false bila sinkron.
     *
     * @param  list<SimulasiJalan>  $daftar
     */
    public function luncurkan(array $daftar): bool
    {
        if (! config('simulasi.latar_belakang', true)) {
            foreach ($daftar as $jalan) {
                try {
                    $this->selesaikan($jalan);
                } catch (Throwable) {
                    // Sudah dicatat gagal dan dibongkar; lanjutkan ruang berikutnya.
                }
            }

            return false;
        }

        try {
            $php = (new PhpExecutableFinder)->find(false) ?: 'php';
            $log = storage_path('logs/simulasi-latar.log');
            $id = implode(' ', array_map(fn (SimulasiJalan $j): string => escapeshellarg((string) $j->getKey()), $daftar));

            $proses = Process::fromShellCommandline(
                'nohup '.escapeshellarg($php).' artisan simulasi:selesaikan '.$id
                .' >> '.escapeshellarg($log).' 2>&1 &',
                base_path(),
            );
            $proses->run();

            if (! $proses->isSuccessful()) {
                throw new \RuntimeException('Proses latar belakang tidak bisa diluncurkan: '.trim($proses->getErrorOutput()));
            }
        } catch (Throwable $galat) {
            foreach ($daftar as $jalan) {
                $this->catatGagal($jalan, $galat->getMessage());
                $jalan->forceFill([
                    'status' => SimulasiJalan::STATUS_GAGAL,
                    'selesai_pada' => now(),
                    'peringatan' => ['galat' => $galat->getMessage()],
                ])->save();

                try {
                    $this->hapus($jalan->fresh());
                } catch (Throwable) {
                }
            }

            throw $galat;
        }

        return true;
    }

    // ── Progres pembangunan ──────────────────────────────────────────────

    private function kunciProgres(SimulasiJalan $jalan): string
    {
        return 'sim-progres:'.$jalan->getKey();
    }

    /** Menutup tahap sebelumnya dan membuka tahap yang baru dilaporkan. */
    private function catatTahap(SimulasiJalan $jalan, string $label): void
    {
        $kunci = $this->kunciProgres($jalan);
        $p = Cache::get($kunci, ['tahap' => []]);
        $sekarang = microtime(true);

        foreach ($p['tahap'] as $nama => $tahap) {
            if ($tahap['selesai'] === null) {
                $p['tahap'][$nama]['selesai'] = $sekarang;
            }
        }

        $p['tahap'][$label] = ['mulai' => $sekarang, 'selesai' => null];
        $p['terakhir'] = $sekarang;

        Cache::put($kunci, $p, now()->addHours(2));
    }

    private function tutupTahapTerbuka(SimulasiJalan $jalan): void
    {
        $kunci = $this->kunciProgres($jalan);
        $p = Cache::get($kunci);

        if (! is_array($p)) {
            return;
        }

        foreach ($p['tahap'] as $nama => $tahap) {
            if ($tahap['selesai'] === null) {
                $p['tahap'][$nama]['selesai'] = microtime(true);
            }
        }

        Cache::put($kunci, $p, now()->addHours(2));
    }

    private function catatGagal(SimulasiJalan $jalan, string $pesan): void
    {
        $kunci = $this->kunciProgres($jalan);
        $p = Cache::get($kunci, ['tahap' => []]);
        $p['gagal'] = true;
        $p['galat'] = $pesan;

        Cache::put($kunci, $p, now()->addHours(2));
    }

    /**
     * Keadaan pembangunan untuk layar progres. Tidak menyentuh data sandbox
     * kecuali menghitung baris yang sudah jadi.
     *
     * @return array{
     *     jalan_id: string, kode: string, mode: string, jumlah_mk: int, status: string,
     *     berjalan: bool, selesai: bool, gagal: bool, macet: bool, galat: ?string,
     *     persen: int, tahap_selesai: int, tahap_total: int, berlalu: int,
     *     tahap: list<array{label: string, keterangan: string, state: string, detik: ?float}>,
     *     cacah: array<string, int>
     * }
     */
    public function progres(SimulasiJalan $jalan): array
    {
        $jalan->refresh();

        $p = Cache::get($this->kunciProgres($jalan));
        $p = is_array($p) ? $p : ['tahap' => []];

        $selesai = $jalan->status === SimulasiJalan::STATUS_SELESAI;
        $gagal = ($p['gagal'] ?? false) || $jalan->status === SimulasiJalan::STATUS_GAGAL;
        $berjalan = $jalan->status === SimulasiJalan::STATUS_BERJALAN && ! $gagal;
        $sekarang = microtime(true);

        $baris = [];
        $nSelesai = 0;
        $adaBerjalan = false;

        foreach (PembangunSimulasi::tahap($jalan->mode) as $tahap) {
            $catatan = $p['tahap'][$tahap['label']] ?? null;

            if ($selesai || ($catatan !== null && $catatan['selesai'] !== null)) {
                $state = 'selesai';
                $detik = $catatan !== null ? max(0.0, $catatan['selesai'] - $catatan['mulai']) : null;
                $nSelesai++;
            } elseif ($catatan !== null) {
                $state = $gagal ? 'gagal' : 'berjalan';
                $detik = max(0.0, $sekarang - $catatan['mulai']);
                $adaBerjalan = $adaBerjalan || ! $gagal;
            } else {
                $state = 'menunggu';
                $detik = null;
            }

            $baris[] = [...$tahap, 'state' => $state, 'detik' => $detik];
        }

        $total = max(1, count($baris));
        $persen = $selesai ? 100 : min(99, (int) floor((($nSelesai + ($adaBerjalan ? 0.5 : 0)) / $total) * 100));

        $akhir = $jalan->selesai_pada ?? now();
        $berlalu = max(0, $akhir->getTimestamp() - ($jalan->mulai_pada ?? now())->getTimestamp());

        // Macet: tidak ada tahap baru lebih dari dua menit padahal masih "berjalan".
        $macet = $berjalan && ($sekarang - (float) ($p['terakhir'] ?? $sekarang)) > 120;

        return [
            'jalan_id' => (string) $jalan->getKey(),
            'kode' => $jalan->kode(),
            'pin' => $jalan->pin,
            'mode' => $jalan->mode,
            'jumlah_mk' => (int) $jalan->jumlah_mk,
            'status' => $jalan->status,
            'berjalan' => $berjalan,
            'selesai' => $selesai,
            'gagal' => $gagal,
            'macet' => $macet,
            'galat' => $gagal ? ($p['galat'] ?? $jalan->peringatan['galat'] ?? 'Penyebab tidak tercatat.') : null,
            'persen' => $persen,
            'tahap_selesai' => $nSelesai,
            'tahap_total' => count($baris),
            'berlalu' => $berlalu,
            'tahap' => $baris,
            'cacah' => $gagal ? [] : $this->cacahLangsung($jalan),
        ];
    }

    /**
     * Banyaknya baris penting yang sudah jadi di sandbox ini saat ini.
     * Dibaca langsung dari tabelnya (bukan dari buku besar artefak yang
     * baru disiram setiap 500 baris), sehingga angkanya naik selama proses.
     *
     * @return array<string, int>
     */
    public function cacahLangsung(SimulasiJalan $jalan): array
    {
        return Ranah::sebagai((string) $jalan->getKey(), function (): array {
            // NilaiMahasiswa tidak punya sandbox_id (turunan kelas lewat CASCADE),
            // jadi dihitung dari kelas milik sandbox ini, bukan dengan count() polos
            // yang akan ikut membaca data inti.
            $idPeserta = KelasMkMahasiswa::query()
                ->whereIn('kelas_mk_id', KelasMk::query()->select('id'))
                ->pluck('id');

            return [
                'Akun' => User::query()->count(),
                'Mahasiswa' => Mahasiswa::query()->count(),
                'Kurikulum' => Kurikulum::query()->count(),
                'CPL' => Cpl::query()->count(),
                'Mata kuliah' => Mk::query()->count(),
                'CPMK' => Cpmk::query()->count(),
                'Kelas' => KelasMk::query()->count(),
                'Nilai' => $idPeserta->isEmpty() ? 0 : NilaiMahasiswa::query()
                    ->whereIn('kelas_mk_mahasiswa_id', $idPeserta)
                    ->count(),
            ];
        });
    }

    /** Mode yang tak dikenal jatuh ke contoh terisi, bukan melempar ke pengunjung. */
    private function mode(string $mode): string
    {
        return in_array($mode, SimulasiJalan::MODE, true) ? $mode : SimulasiJalan::MODE_TERISI;
    }

    public function hapus(SimulasiJalan $jalan, bool $terapkan = true): HasilPembongkaran
    {
        if ($terapkan) {
            app(RuangSimulasi::class)->lepasSemuaDiRuang((string) $jalan->getKey());
        }

        return $this->pembongkar->bongkar($jalan, $terapkan);
    }

    /**
     * Membongkar seluruh sandbox. Tidak menyentuh data inti.
     *
     * @return int jumlah sandbox yang dibongkar
     */
    public function hapusSemua(): int
    {
        // Contoh terisi bersama sengaja dilewati: ia bagian dari panduan, bukan ruang latihan.
        $semua = SimulasiJalan::query()->masihAda()->where('bersama', false)->get();

        foreach ($semua as $jalan) {
            $this->hapus($jalan);
        }

        return $semua->count();
    }

    // ── Sakelar mode latihan ─────────────────────────────────────────────

    /**
     * Apakah tombol "Coba sebagai ‹peran›" sedang terbuka.
     *
     * Dua syarat: instans mengizinkannya (pemutus keras di config/.env) DAN
     * Super Admin menyalakannya. Sakelar kini global (tabel simulasi_pengaturan),
     * bukan menempel pada satu sandbox, karena sandbox dibuat sesuai permintaan.
     */
    public function cobaPeranTerbuka(): bool
    {
        return (bool) config('simulasi.izinkan_coba_peran', true)
            && DB::table('simulasi_pengaturan')->where('kunci', self::KUNCI_COBA_PERAN)->value('nilai') === '1';
    }

    public function cobaPeranDilarangInstans(): bool
    {
        return ! (bool) config('simulasi.izinkan_coba_peran', true);
    }

    public function aturCobaPeran(bool $nyala): void
    {
        DB::table('simulasi_pengaturan')->updateOrInsert(
            ['kunci' => self::KUNCI_COBA_PERAN],
            ['nilai' => $nyala ? '1' : '0', 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * Gerbang untuk tombol "Coba sebagai ‹peran›": hanya baris yang benar-benar
     * DIBUAT oleh sandbox ini yang dianggap miliknya. Akun nyata bernama sama
     * tidak punya artefak sehingga tidak pernah lolos.
     */
    /**
     * Apakah semua akun simulasi yang dibutuhkan panduan ada di sandbox ini.
     * Sandbox hasil versi lama hanya punya sebagian akun dan bernama tanpa akhiran.
     */
    public function akunLengkap(SimulasiJalan $jalan): bool
    {
        $dibutuhkan = collect(array_keys(AkunSimulasi::akun()))
            ->map(fn (string $kunci): string => AkunSimulasi::username($kunci, $jalan->kode()))
            ->all();

        $ada = Ranah::sebagai((string) $jalan->getKey(), fn () => User::query()
            ->whereIn('username', $dibutuhkan)->count());

        return $ada === count($dibutuhkan);
    }

    public function memiliki(SimulasiJalan $jalan, Model $model): bool
    {
        return SimulasiArtefak::query()
            ->where('simulasi_jalan_id', $jalan->getKey())
            ->where('model_type', $model->getMorphClass())
            ->where('model_uuid', (string) $model->getKey())
            ->exists();
    }
}
