<?php

namespace App\Modules\Auth\Support;

use App\Modules\Kalender\Models\Semester;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Memastikan sebuah surel adalah dosen pengampu, lewat API Sintesys: surel
 * dianggap dosen bila Sintesys mengembalikan sedikitnya satu kelas yang
 * diampunya pada tahun akademik aktif. Dipakai saat masuk dengan Google untuk
 * memberi peran "Dosen Pengampu" otomatis tanpa permintaan ke admin.
 *
 * Gagal tertutup: bila Sintesys tidak dapat dijangkau atau belum dikonfigurasi,
 * hasilnya `tidak_tersedia` dan TIDAK ada peran yang diberikan.
 */
class VerifikasiDosenSintesys
{
    public const DOSEN = 'dosen';

    public const BUKAN_DOSEN = 'bukan_dosen';

    public const TIDAK_TERSEDIA = 'tidak_tersedia';

    /**
     * @return array{status: string, kode_prodi: list<string>}
     */
    public function periksa(string $email): array
    {
        $email = strtolower(trim($email));
        $semester = Semester::query()->where('status_aktif', true)->first()
            ?? Semester::query()->orderByDesc('kode')->first();

        $endpoint = (string) config('services.sintesys.endpoint');
        $token = (string) config('services.sintesys.token');

        if ($email === '' || $semester === null || $endpoint === '' || $token === '') {
            return $this->hasil(self::TIDAK_TERSEDIA);
        }

        $kunci = sprintf('masuk-google:sintesys:%s:%s', $semester->kode, sha1($email));

        $tersimpan = Cache::get($kunci);
        if (is_array($tersimpan)) {
            return $tersimpan;
        }

        try {
            $respons = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->post($endpoint, [
                    'tahun_akademik' => $semester->kode,
                    'kode_prodi' => null,
                    'kode_matakuliah' => null,
                    'nuptk' => null,
                    'email' => $email,
                ]);
        } catch (\Throwable) {
            return $this->hasil(self::TIDAK_TERSEDIA);
        }

        $payload = $respons->failed() ? null : $respons->json();

        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            return $this->hasil(self::TIDAK_TERSEDIA);
        }

        $kodeProdi = [];
        foreach ($payload['data'] as $kelas) {
            if (! is_array($kelas)) {
                continue;
            }

            $kode = trim((string) ($kelas['kode_prodi'] ?? $payload['kode_prodi'] ?? ''));
            if ($kode !== '') {
                $kodeProdi[$kode] = $kode;
            }
        }

        $hasil = $payload['data'] === []
            ? $this->hasil(self::BUKAN_DOSEN)
            : $this->hasil(self::DOSEN, array_values($kodeProdi));

        // Hasil pasti (dosen / bukan dosen) disimpan singkat agar percobaan
        // masuk berulang tidak menembak API; kegagalan tidak pernah disimpan.
        Cache::put($kunci, $hasil, now()->addMinutes(10));

        return $hasil;
    }

    /**
     * @param  list<string>  $kodeProdi
     * @return array{status: string, kode_prodi: list<string>}
     */
    private function hasil(string $status, array $kodeProdi = []): array
    {
        return ['status' => $status, 'kode_prodi' => $kodeProdi];
    }
}
