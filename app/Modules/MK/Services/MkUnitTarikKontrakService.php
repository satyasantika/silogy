<?php

namespace App\Modules\MK\Services;

use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Services\PesertaKelasSintesysImportService;
use App\Modules\MK\Models\MkUnit;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Tarik kontrak kelas + peserta dari Sintesys/Simak untuk satu penawaran MK
 * pada semester kalender tertentu. Dipakai EditMkUnit dan aksi list /mk-units.
 */
class MkUnitTarikKontrakService
{
    /**
     * Semester kode ≥ nilai ini memakai Sintesys; sebelumnya memakai Simak.
     */
    public const KODE_AWAL_SINTESYS = '20251';

    /**
     * @return 'sintesys'|'simak'
     */
    public function sumberUntukSemester(?Semester $semester): string
    {
        if (! $semester instanceof Semester || blank($semester->kode)) {
            return 'sintesys';
        }

        return strcmp((string) $semester->kode, self::KODE_AWAL_SINTESYS) >= 0
            ? 'sintesys'
            : 'simak';
    }

    public function labelSumber(string $sumber): string
    {
        return $sumber === 'simak' ? 'Simak' : 'Sintesys';
    }

    /**
     * @return array{
     *     status: 'ok'|'kosong'|'error'|'validasi',
     *     judul: string,
     *     pesan: string,
     *     hasil: ?array{
     *         kelas_dibuat: int,
     *         kelas_diperbarui: int,
     *         peserta_terdaftar: int,
     *         peserta_sudah_terdaftar: int,
     *         errors: list<string>
     *     }
     * }
     */
    public function tarik(MkUnit $mkUnit, Semester $semester): array
    {
        $mkUnit->loadMissing(['academicUnit', 'mk']);

        $sumber = $this->sumberUntukSemester($semester);
        $labelSumber = $this->labelSumber($sumber);

        if (blank($mkUnit->kode) || blank($mkUnit->academicUnit?->code)) {
            return [
                'status' => 'validasi',
                'judul' => 'Kode penawaran atau kode prodi belum lengkap',
                'pesan' => "Pastikan penawaran MK punya kode dan unit penawaran prodi sebelum menarik data dari {$labelSumber}.",
                'hasil' => null,
            ];
        }

        $preview = $this->preview($mkUnit, $semester, $sumber);

        if ($preview['status'] !== 'ok') {
            return [
                'status' => $preview['status'],
                'judul' => $preview['status'] === 'kosong'
                    ? 'Tidak ada data kontrak pada semester ini'
                    : "Gagal menarik data dari {$labelSumber}",
                'pesan' => (string) ($preview['pesan'] ?? 'Periksa koneksi atau coba semester lain.'),
                'hasil' => null,
            ];
        }

        try {
            $hasil = app(PesertaKelasSintesysImportService::class)
                ->import($preview['payload'] ?? [], $mkUnit, $semester);
        } catch (ValidationException $exception) {
            return [
                'status' => 'error',
                'judul' => 'Impor gagal',
                'pesan' => collect($exception->errors())->flatten()->join(' '),
                'hasil' => null,
            ];
        }

        Cache::forget($this->cacheKey($mkUnit, $semester, $sumber));

        return [
            'status' => 'ok',
            'judul' => sprintf(
                'Tarik kontrak selesai (%s) — %s · %s',
                $labelSumber,
                $mkUnit->kode,
                $semester->nama,
            ),
            'pesan' => '',
            'hasil' => $hasil,
        ];
    }

    /**
     * @param  array{
     *     status: 'ok'|'kosong'|'error'|'validasi',
     *     judul: string,
     *     pesan: string,
     *     hasil: ?array{
     *         kelas_dibuat: int,
     *         kelas_diperbarui: int,
     *         peserta_terdaftar: int,
     *         peserta_sudah_terdaftar: int,
     *         errors: list<string>
     *     }
     * }  $result
     */
    public function kirimNotifikasi(array $result): void
    {
        if ($result['status'] !== 'ok' || $result['hasil'] === null) {
            $notification = Notification::make()
                ->title($result['judul'])
                ->body($result['pesan']);

            if ($result['status'] === 'validasi' || $result['status'] === 'error') {
                $notification->danger()->persistent();
            } else {
                $notification->warning();
            }

            $notification->send();

            return;
        }

        $hasil = $result['hasil'];
        $total = $hasil['kelas_dibuat'] + $hasil['kelas_diperbarui']
            + $hasil['peserta_terdaftar'] + $hasil['peserta_sudah_terdaftar'];
        $totalGagal = count($hasil['errors']);

        $ringkasan = sprintf(
            'Kelas dibuat: %d · Kelas diperbarui: %d · Peserta terdaftar: %d · Gagal: %d',
            $hasil['kelas_dibuat'],
            $hasil['kelas_diperbarui'],
            $hasil['peserta_terdaftar'] + $hasil['peserta_sudah_terdaftar'],
            $totalGagal,
        );

        $detailGagal = $hasil['errors'] === []
            ? ''
            : "\n".implode("\n", array_slice($hasil['errors'], 0, 8)).(count($hasil['errors']) > 8 ? "\n…" : '');

        $notification = Notification::make()
            ->title($result['judul'])
            ->body($ringkasan.$detailGagal);

        if ($total > 0 && $totalGagal === 0) {
            $notification->success();
        } elseif ($total > 0) {
            $notification->warning()->persistent();
        } else {
            $notification->danger()->persistent();
        }

        $notification->send();
    }

    /**
     * @param  'sintesys'|'simak'  $sumber
     * @return array{status: 'ok'|'kosong'|'error', pesan: ?string, payload: ?array<string, mixed>, jumlah_kelas: int, jumlah_peserta: int}
     */
    public function preview(MkUnit $mkUnit, Semester $semester, string $sumber): array
    {
        $cacheKey = $this->cacheKey($mkUnit, $semester, $sumber);
        $labelSumber = $this->labelSumber($sumber);

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($mkUnit, $semester, $sumber, $labelSumber): array {
            $endpoint = (string) config("services.{$sumber}.endpoint");
            $token = (string) config("services.{$sumber}.token");

            if ($endpoint === '' || $token === '') {
                return [
                    'status' => 'error',
                    'pesan' => "Konfigurasi API {$labelSumber} (endpoint/token) belum diisi pada server.",
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            $mkUnit->loadMissing('academicUnit');

            $body = [
                'tahun_akademik' => is_numeric($semester->kode) ? (int) $semester->kode : $semester->kode,
                'kode_prodi' => (string) $mkUnit->academicUnit?->code,
                'kode_matakuliah' => $mkUnit->kode,
            ];

            try {
                $response = Http::withToken($token)
                    ->timeout(60)
                    ->post($endpoint, $body);
            } catch (\Throwable $exception) {
                return [
                    'status' => 'error',
                    'pesan' => $exception->getMessage(),
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            if ($response->failed()) {
                return [
                    'status' => 'error',
                    'pesan' => "Permintaan API {$labelSumber} gagal (HTTP {$response->status()}).",
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                return [
                    'status' => 'error',
                    'pesan' => "Respons API {$labelSumber} bukan JSON yang valid.",
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            $data = $payload['data'] ?? null;
            $jumlahKelas = is_array($data) ? count($data) : 0;
            $jumlahPeserta = 0;

            if (is_array($data)) {
                foreach ($data as $item) {
                    if (is_array($item) && is_array($item['peserta'] ?? null)) {
                        $jumlahPeserta += count($item['peserta']);
                    }
                }
            }

            if ($jumlahKelas === 0) {
                return [
                    'status' => 'kosong',
                    'pesan' => sprintf(
                        'Tidak ada kontrak untuk %s pada tahun akademik %s.',
                        $mkUnit->kode,
                        $semester->kode,
                    ),
                    'payload' => $payload,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            return [
                'status' => 'ok',
                'pesan' => null,
                'payload' => $payload,
                'jumlah_kelas' => $jumlahKelas,
                'jumlah_peserta' => $jumlahPeserta,
            ];
        });
    }

    protected function cacheKey(MkUnit $mkUnit, Semester $semester, string $sumber): string
    {
        return sprintf(
            'kontrak-preview-mk-unit:%s:%s:%s:%s',
            $sumber,
            auth()->id(),
            $mkUnit->id,
            $semester->id,
        );
    }
}
