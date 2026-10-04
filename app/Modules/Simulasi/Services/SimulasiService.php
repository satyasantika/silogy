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
use App\Modules\Simulasi\Support\Ranah;
use Database\Seeders\Support\SimulasiAkademikBuilder;
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

    // ── Pengunjung & kolam ───────────────────────────────────────────────

    public static function hashPengunjung(string $pengenal): string
    {
        return hash('sha256', $pengenal);
    }

    /**
     * Sandbox milik pengunjung ini pada mode tertentu: yang sudah ia punya,
     * atau satu dari kolam, atau yang dibangun saat itu juga. Null bila
     * kapasitas penuh. Satu pengunjung dapat memegang dua sandbox sekaligus,
     * satu per mode, sehingga contoh terisi dan contoh kosong tidak saling
     * menimpa.
     */
    public function klaim(string $hashPengunjung, string $mode = SimulasiJalan::MODE_TERISI): ?SimulasiJalan
    {
        $mode = $this->mode($mode);

        return Cache::lock('sim-klaim:'.$mode.':'.$hashPengunjung, 180)->block(120, function () use ($hashPengunjung, $mode): ?SimulasiJalan {
            $milik = SimulasiJalan::query()
                ->where('status', SimulasiJalan::STATUS_SELESAI)
                ->where('pengunjung', $hashPengunjung)
                ->where('mode', $mode)
                ->first();

            if ($milik !== null) {
                if ($this->akunLengkap($milik)) {
                    return $milik;
                }

                // Sandbox peninggalan versi lama (akun belum 3 level) tidak bisa
                // dipakai masuk. Dibongkar lalu diganti yang baru.
                $this->hapus($milik);
            }

            do {
                $dariKolam = DB::transaction(function () use ($hashPengunjung, $mode): ?SimulasiJalan {
                    $jalan = SimulasiJalan::query()->siap($mode)->orderBy('selesai_pada')->lockForUpdate()->first();

                    $jalan?->forceFill([
                        'pengunjung' => $hashPengunjung,
                        'terakhir_aktif_pada' => now(),
                    ])->save();

                    return $jalan;
                });

                if ($dariKolam !== null && ! $this->akunLengkap($dariKolam)) {
                    $this->hapus($dariKolam);
                    $dariKolam = false;
                }
            } while ($dariKolam === false);

            if ($dariKolam !== null) {
                return $dariKolam;
            }

            try {
                return $this->buat(pengunjung: $hashPengunjung, mode: $mode)->jalan;
            } catch (KapasitasSandboxPenuhException) {
                return null;
            }
        });
    }

    /**
     * Menambah sandbox siap-pakai sampai kolam tiap mode mencapai targetnya.
     *
     * @param  callable(string): void|null  $lapor
     * @return int jumlah sandbox yang berhasil dibuat
     */
    public function isiKolam(?callable $lapor = null): int
    {
        $dibuat = 0;

        $targetPerMode = [
            SimulasiJalan::MODE_TERISI => max(0, (int) config('simulasi.kolam_siap', 2)),
            SimulasiJalan::MODE_KOSONG => max(0, (int) config('simulasi.kolam_siap_kosong', 2)),
        ];

        foreach ($targetPerMode as $mode => $target) {
            while (SimulasiJalan::query()->siap($mode)->count() < $target) {
                try {
                    $this->buat(lapor: $lapor, mode: $mode);
                    $dibuat++;
                } catch (KapasitasSandboxPenuhException) {
                    return $dibuat;
                } catch (Throwable) {
                    // Build gagal sudah dibongkar di buat(); hentikan putaran mode
                    // ini supaya kegagalan yang deterministik tidak berulang tanpa
                    // akhir, lalu coba mode berikutnya.
                    break;
                }
            }
        }

        return $dibuat;
    }

    /**
     * Membuang sandbox yang tak aktif melebihi batas umur, sandbox gagal,
     * dan baris riwayat lama. Sandbox kolam yang belum diklaim dipertahankan.
     *
     * @return int jumlah sandbox yang dibongkar
     */
    public function bersihkanKedaluwarsa(): int
    {
        $batas = now()->subMinutes(max(1, (int) config('simulasi.umur_menit', 120)));

        $usang = SimulasiJalan::query()->masihAda()
            ->where(function ($q) use ($batas): void {
                $q->where('status', SimulasiJalan::STATUS_GAGAL)
                    ->orWhere(fn ($q) => $q->where('status', SimulasiJalan::STATUS_BERJALAN)
                        ->where('mulai_pada', '<', now()->subMinutes(30)))
                    ->orWhere(fn ($q) => $q->whereNotNull('pengunjung')
                        ->whereRaw('coalesce(terakhir_aktif_pada, mulai_pada) < ?', [$batas]));
            })
            ->get();

        foreach ($usang as $jalan) {
            $this->hapus($jalan);
        }

        SimulasiJalan::query()
            ->where('status', SimulasiJalan::STATUS_DIBONGKAR)
            ->where('dibongkar_pada', '<', now()->subDays(7))
            ->delete();

        return $usang->count();
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

    /**
     * Membangun satu sandbox baru, dari awal sampai selesai, dalam satu panggilan.
     *
     * @param  callable(string): void|null  $lapor
     */
    public function buat(
        ?User $pemicu = null,
        ?callable $lapor = null,
        ?string $pengunjung = null,
        string $mode = SimulasiJalan::MODE_TERISI,
        ?int $jumlahMk = null,
    ): HasilPembangunan {
        $jalan = $this->mulai($pemicu, $pengunjung, $mode, $jumlahMk);

        return $this->selesaikan($jalan, $lapor);
    }

    /**
     * Tahap 1 pembangunan: memeriksa kapasitas dan mendaftarkan baris jalan.
     * Dipisah dari selesaikan() supaya antarmuka bisa menampilkan progres
     * sandbox yang sudah punya ID, sementara pembangunannya berjalan.
     */
    public function mulai(
        ?User $pemicu = null,
        ?string $pengunjung = null,
        string $mode = SimulasiJalan::MODE_TERISI,
        ?int $jumlahMk = null,
    ): SimulasiJalan {
        $mode = $this->mode($mode);
        $jumlahMk = $mode === SimulasiJalan::MODE_KOSONG
            ? 0
            : max(1, min($jumlahMk ?? (int) config('simulasi.jumlah_mk', 6), SimulasiAkademikBuilder::MAKS_MK));

        $maks = max(1, (int) config('simulasi.maks_sandbox', 20));

        if (SimulasiJalan::query()->masihAda()->count() >= $maks) {
            throw KapasitasSandboxPenuhException::buat($maks);
        }

        // Baris jalan disimpan LEBIH DULU dan di luar transaksi pembangunan:
        // artefak yang terlanjur lahir tetap tercatat sehingga bisa dibongkar.
        $jalan = SimulasiJalan::query()->create([
            'status' => SimulasiJalan::STATUS_BERJALAN,
            'dipicu_oleh_id' => $pemicu?->getKey(),
            'mode' => $mode,
            'jumlah_mk' => $jumlahMk,
            'pengunjung' => $pengunjung,
            'mulai_pada' => now(),
            'terakhir_aktif_pada' => $pengunjung === null ? null : now(),
        ]);

        Cache::put($this->kunciProgres($jalan), ['tahap' => [], 'terakhir' => microtime(true)], now()->addHours(2));

        return $jalan;
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

    /**
     * Menjalankan selesaikan() untuk sandbox yang sudah didaftarkan mulai().
     *
     * Dengan config simulasi.latar_belakang aktif, pembangunan berjalan di
     * proses PHP terpisah (artisan simulasi:selesaikan) sehingga permintaan web
     * langsung kembali dan progresnya bisa dipantau. Bila proses tidak bisa
     * diluncurkan, sandbox ditandai gagal dan dibongkar, bukan dibiarkan
     * menggantung.
     *
     * @return bool true bila berjalan di latar belakang, false bila sudah selesai di tempat
     */
    public function luncurkan(SimulasiJalan $jalan): bool
    {
        if (! config('simulasi.latar_belakang', true)) {
            $this->selesaikan($jalan);

            return false;
        }

        try {
            $php = (new PhpExecutableFinder)->find(false) ?: 'php';
            $log = storage_path('logs/simulasi-latar.log');

            $proses = Process::fromShellCommandline(
                'nohup '.escapeshellarg($php).' artisan simulasi:selesaikan '.escapeshellarg((string) $jalan->getKey())
                .' >> '.escapeshellarg($log).' 2>&1 &',
                base_path(),
            );
            $proses->run();

            if (! $proses->isSuccessful()) {
                throw new \RuntimeException('Proses latar belakang tidak bisa diluncurkan: '.trim($proses->getErrorOutput()));
            }
        } catch (Throwable $galat) {
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
        return $this->pembongkar->bongkar($jalan, $terapkan);
    }

    /**
     * Membongkar seluruh sandbox. Tidak menyentuh data inti.
     *
     * @return int jumlah sandbox yang dibongkar
     */
    public function hapusSemua(): int
    {
        $semua = SimulasiJalan::query()->masihAda()->get();

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
