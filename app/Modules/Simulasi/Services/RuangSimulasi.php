<?php

namespace App\Modules\Simulasi\Services;

use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Simulasi\Models\SimulasiJalan;
use App\Modules\Simulasi\Models\SimulasiPeranTerisi;
use App\Modules\Simulasi\Support\PengaturanSimulasi;
use App\Modules\Simulasi\Support\SesiTab;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Ruang latihan bertoken: peserta memasukkan token ruang lalu memilih satu
 * peran. Satu peran hanya boleh dipegang satu tab per ruang; pemegangnya
 * dicatat di simulasi_peran_terisi dengan kunci unik (ruang, peran), jadi dua
 * peserta yang menekan peran yang sama bersamaan tidak mungkin lolos
 * keduanya, apa pun yang dilakukan PHP.
 */
class RuangSimulasi
{
    /** Ruang latihan yang siap dimasuki berdasarkan token (huruf besar/kecil dan pemisah diabaikan). */
    public function cari(string $masukan): ?SimulasiJalan
    {
        $pin = SimulasiService::normalisasiPin($masukan);

        if (strlen($pin) !== 6) {
            return null;
        }

        return SimulasiJalan::query()
            ->where('bersama', false)
            ->where('mode', SimulasiJalan::MODE_KOSONG)
            ->where('status', SimulasiJalan::STATUS_SELESAI)
            ->where('pin', $pin)
            ->first();
    }

    /** Batas waktu: pemegang yang terakhir aktif sebelum ini dianggap sudah meninggalkan perannya. */
    public function batasSewa(): Carbon
    {
        return now()->subMinutes(PengaturanSimulasi::ambil('sewa_menit'));
    }

    /**
     * Enam peran beserta status terisinya, urutan sama dengan halaman panduan.
     *
     * @return array<string, array{label: string, ikon: string, ringkas: string, terisi: bool}>
     */
    public function daftarPeran(SimulasiJalan $ruang): array
    {
        $terisi = SimulasiPeranTerisi::query()
            ->where('simulasi_jalan_id', $ruang->getKey())
            ->where('terakhir_aktif_pada', '>=', $this->batasSewa())
            ->pluck('peran')
            ->all();

        $hasil = [];

        foreach (PeranPanduan::bisaDicoba() as $slug => $definisi) {
            $hasil[$slug] = [
                'label' => $definisi['label'],
                'ikon' => $definisi['ikon'],
                'ringkas' => $definisi['ringkas'],
                'terisi' => in_array($slug, $terisi, true),
            ];
        }

        return $hasil;
    }

    /**
     * Peran yang sedang dipegang tiap ruang, untuk tampilan Super Admin.
     *
     * @return array<string, list<string>> id ruang → daftar slug peran
     */
    public function peranTerisiSemua(): array
    {
        return SimulasiPeranTerisi::query()
            ->where('terakhir_aktif_pada', '>=', $this->batasSewa())
            ->get(['simulasi_jalan_id', 'peran'])
            ->groupBy('simulasi_jalan_id')
            ->map(fn ($baris): array => $baris->pluck('peran')->values()->all())
            ->all();
    }

    /**
     * Mengambil satu peran untuk satu tab. Mengembalikan false bila peran itu
     * sudah dipegang tab lain yang masih aktif.
     *
     * Sengaja tanpa transaksi dan tanpa DELETE lebih dulu: penyisipan langsung
     * dan UPDATE kondisional atomik menghindari kunci celah InnoDB yang bisa
     * membuat dua peserta serentak saling deadlock.
     */
    public function ambilPeran(SimulasiJalan $ruang, string $slug, string $tabToken): bool
    {
        try {
            SimulasiPeranTerisi::query()->create([
                'simulasi_jalan_id' => $ruang->getKey(),
                'peran' => $slug,
                'tab_token' => $tabToken,
                'terakhir_aktif_pada' => now(),
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            // Peran sudah tercatat. Hanya boleh direbut bila pemegangnya sudah
            // melewati batas sewa; UPDATE ini atomik sehingga hanya satu penawar menang.
            $diambilAlih = SimulasiPeranTerisi::query()
                ->where('simulasi_jalan_id', $ruang->getKey())
                ->where('peran', $slug)
                ->where('terakhir_aktif_pada', '<', $this->batasSewa())
                ->update(['tab_token' => $tabToken, 'terakhir_aktif_pada' => now(), 'updated_at' => now()]);

            return $diambilAlih === 1;
        }
    }

    /**
     * Apakah tab ini masih pemegang perannya. Sekaligus memperpanjang sewanya
     * (paling sering sekali per menit). Tab yang perannya sudah diambil orang
     * lain, dilepas, atau ruangnya dihapus mengembalikan false.
     */
    public function masihPemegang(string $tabToken): bool
    {
        $ada = SimulasiPeranTerisi::query()->where('tab_token', $tabToken)->exists();

        if (! $ada) {
            return false;
        }

        if (Cache::add('sim-sewa:'.$tabToken, 1, 60)) {
            SimulasiPeranTerisi::query()
                ->where('tab_token', $tabToken)
                ->update(['terakhir_aktif_pada' => now(), 'updated_at' => now()]);
        }

        return true;
    }

    public function lepasTab(string $tabToken): void
    {
        SimulasiPeranTerisi::query()->where('tab_token', $tabToken)->delete();
        Cache::forget('sim-sewa:'.$tabToken);
    }

    /** Dipakai Super Admin: kosongkan satu peran di satu ruang. */
    public function lepasPeran(string $ruangId, string $slug): int
    {
        return SimulasiPeranTerisi::query()
            ->where('simulasi_jalan_id', $ruangId)
            ->where('peran', $slug)
            ->delete();
    }

    public function lepasSemuaDiRuang(string $ruangId): void
    {
        SimulasiPeranTerisi::query()->where('simulasi_jalan_id', $ruangId)->delete();
    }

    /**
     * Dipanggil saat pengguna menekan Keluar di dalam tab ruang: peran dilepas
     * dan catatan tab dilupakan. Mengembalikan alamat halaman ruang (di luar
     * awalan tab) untuk tujuan pengalihan, atau null bila tab ini bukan tab ruang.
     */
    public function lepasTabSaatIni(): ?string
    {
        $tab = request()->attributes->get('sandbox_tab');

        if (! is_array($tab) || ! ($tab['ruang'] ?? false)) {
            return null;
        }

        $pin = SimulasiJalan::query()->whereKey($tab['jalan'])->value('pin');

        $this->lepasTab($tab['token']);
        SesiTab::lupakan($tab['token']);

        if (! is_string($pin) || $pin === '') {
            return null;
        }

        // route() di dalam tab memakai root URL berawalan /s/<token>; awalan itu dibuang.
        return str_replace('/s/'.$tab['token'], '', route('simulasi.ruang.tampil', ['pin' => $pin]));
    }
}
