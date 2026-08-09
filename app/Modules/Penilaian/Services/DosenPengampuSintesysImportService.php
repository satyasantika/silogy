<?php

namespace App\Modules\Penilaian\Services;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Mahasiswa\Support\AngkatanDariNim;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Tarik kelas + peserta dari Sintesys untuk Dosen Pengampu, difilter email
 * dosen yang sedang login (halaman /penilaian). Email dipakai (bukan NIDN)
 * karena email dijamin terisi (identitas wajib), sedangkan NIDN opsional.
 */
class DosenPengampuSintesysImportService
{
    /**
     * @return array{status: 'ok'|'kosong'|'error'|'validasi', pesan: ?string, payload: ?array<string, mixed>, jumlah_kelas: int, jumlah_peserta: int}
     */
    public function preview(User $dosen, Semester $semester): array
    {
        $email = trim((string) $dosen->email);

        $cacheKey = sprintf('sintesys-preview-dosen:%s:%s:%s', $dosen->id, $semester->id, $email);

        return Cache::remember($cacheKey, now()->addMinutes(3), function () use ($semester, $email): array {
            $endpoint = (string) config('services.sintesys.endpoint');
            $token = (string) config('services.sintesys.token');

            if ($endpoint === '' || $token === '') {
                return [
                    'status' => 'error',
                    'pesan' => 'Konfigurasi API Sintesys (endpoint/token) belum diisi pada server.',
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            try {
                $response = Http::withToken($token)
                    ->timeout(60)
                    ->post($endpoint, [
                        'tahun_akademik' => $semester->kode,
                        'kode_prodi' => null,
                        'kode_matakuliah' => null,
                        'nuptk' => null,
                        'email' => $email,
                    ]);
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
                    'pesan' => "Permintaan API Sintesys gagal (HTTP {$response->status()}).",
                    'payload' => null,
                    'jumlah_kelas' => 0,
                    'jumlah_peserta' => 0,
                ];
            }

            $payload = $response->json();

            if (! is_array($payload)) {
                return [
                    'status' => 'error',
                    'pesan' => 'Respons API Sintesys bukan JSON yang valid.',
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
                        'Tidak ada kelas diampu untuk email %s pada tahun akademik %s.',
                        $email,
                        $semester->kode,
                    ),
                    'payload' => null,
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

    public function ringkasanPreviewHtml(User $dosen, Semester $semester, array $preview): string
    {
        $email = trim((string) $dosen->email) ?: '—';

        return match ($preview['status']) {
            'validasi', 'error' => sprintf(
                '<p style="font-size:13px;color:#991b1b;">%s</p>',
                e((string) ($preview['pesan'] ?? 'Terjadi kesalahan.')),
            ),
            'kosong' => sprintf(
                '<p style="font-size:13px;color:#92400e;">Tidak ada data ditemukan dari Sintesys untuk email <strong>%s</strong> '
                .'· tahun akademik <strong>%s</strong>. Tidak ada yang bisa diimpor.</p>',
                e($email),
                e($semester->nama),
            ),
            default => sprintf(
                '<p style="font-size:13px;color:#166534;">Ditemukan <strong>%d kelas</strong> dan <strong>%d peserta</strong> '
                .'siap diimpor dari Sintesys untuk email <strong>%s</strong> · tahun akademik <strong>%s</strong>.</p>',
                (int) $preview['jumlah_kelas'],
                (int) $preview['jumlah_peserta'],
                e($email),
                e($semester->nama),
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     kelas_dibuat: int,
     *     kelas_diperbarui: int,
     *     peserta_terdaftar: int,
     *     peserta_sudah_terdaftar: int,
     *     mahasiswa_dibuat: int,
     *     errors: list<string>
     * }
     */
    public function import(array $payload, User $dosen, Semester $semester): array
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            throw ValidationException::withMessages([
                'sumber' => 'Struktur JSON tidak valid: wajib ada "data" (array).',
            ]);
        }

        $kelasDibuat = 0;
        $kelasDiperbarui = 0;
        $pesertaTerdaftar = 0;
        $pesertaSudahTerdaftar = 0;
        $mahasiswaDibuat = 0;
        $errors = [];

        foreach ($data as $index => $item) {
            $baris = $index + 1;

            if (! is_array($item)) {
                $errors[] = "Data ke-{$baris}: format tidak valid.";

                continue;
            }

            $kodeMk = trim((string) ($item['kode_mk'] ?? $item['kode_matakuliah'] ?? ''));
            $kodeKelas = trim((string) ($item['kelas'] ?? ''));

            if ($kodeMk === '' || $kodeKelas === '') {
                $errors[] = "Data ke-{$baris}: kode_mk atau kelas kosong.";

                continue;
            }

            $kodeProdi = trim((string) (
                $item['kode_prodi']
                ?? $payload['kode_prodi']
                ?? ''
            ));

            $mkUnit = $this->cariMkUnit($kodeMk, $kodeProdi !== '' ? $kodeProdi : null);

            if (! $mkUnit instanceof MkUnit) {
                $errors[] = $kodeProdi !== ''
                    ? "Data ke-{$baris}: MK \"{$kodeMk}\" tidak ditemukan pada prodi \"{$kodeProdi}\"."
                    : "Data ke-{$baris}: MK dengan kode \"{$kodeMk}\" tidak ditemukan di penawaran manapun.";

                continue;
            }

            $kelas = KelasMk::query()
                ->where('mk_unit_id', $mkUnit->id)
                ->where('semester_id', $semester->id)
                ->where('kode_kelas', $kodeKelas)
                ->first();

            if ($kelas instanceof KelasMk) {
                $kelasDiperbarui++;
                $kelas->update(['dosen_pengampu_id' => $dosen->id]);
            } else {
                $kelas = KelasMk::query()->create([
                    'mk_unit_id' => $mkUnit->id,
                    'semester_id' => $semester->id,
                    'kode_kelas' => $kodeKelas,
                    'dosen_pengampu_id' => $dosen->id,
                    'koordinator_mk_id' => $mkUnit->mk?->koordinator_mk_id,
                ]);
                $kelasDibuat++;
            }

            $peserta = $item['peserta'] ?? [];

            if (! is_array($peserta)) {
                continue;
            }

            $unitId = $mkUnit->academic_unit_id;

            foreach ($peserta as $p) {
                if (! is_array($p)) {
                    continue;
                }

                $npm = trim((string) ($p['npm'] ?? $p['nim'] ?? ''));

                if ($npm === '') {
                    continue;
                }

                $mahasiswa = Mahasiswa::query()
                    ->where('nim', $npm)
                    ->where('academic_unit_id', $unitId)
                    ->first();

                if (! $mahasiswa instanceof Mahasiswa) {
                    if (Mahasiswa::query()->where('nim', $npm)->exists()) {
                        $errors[] = "Data ke-{$baris} ({$kodeMk}/{$kodeKelas}): mahasiswa NPM \"{$npm}\" sudah terdaftar pada prodi lain.";

                        continue;
                    }

                    $namaMahasiswa = trim((string) ($p['nama'] ?? $p['nama_mahasiswa'] ?? ''));

                    $mahasiswa = Mahasiswa::query()->create([
                        'nim' => $npm,
                        'nama' => $namaMahasiswa !== '' ? $namaMahasiswa : null,
                        'academic_unit_id' => $unitId,
                        'angkatan' => AngkatanDariNim::dari($npm),
                    ]);
                    $mahasiswaDibuat++;
                } else {
                    AngkatanDariNim::isiBilaKosong($mahasiswa);
                }

                $pivot = KelasMkMahasiswa::query()->firstOrCreate([
                    'kelas_mk_id' => $kelas->id,
                    'mahasiswa_id' => $mahasiswa->id,
                ]);

                if ($pivot->wasRecentlyCreated) {
                    $pesertaTerdaftar++;
                } else {
                    $pesertaSudahTerdaftar++;
                }
            }
        }

        return [
            'kelas_dibuat' => $kelasDibuat,
            'kelas_diperbarui' => $kelasDiperbarui,
            'peserta_terdaftar' => $pesertaTerdaftar,
            'peserta_sudah_terdaftar' => $pesertaSudahTerdaftar,
            'mahasiswa_dibuat' => $mahasiswaDibuat,
            'errors' => $errors,
        ];
    }

    protected function cariMkUnit(string $kodeMk, ?string $kodeProdi): ?MkUnit
    {
        $query = MkUnit::query()->with(['mk', 'academicUnit'])->where('kode', $kodeMk);

        if (filled($kodeProdi)) {
            $query->whereHas(
                'academicUnit',
                fn ($unitQuery) => $unitQuery->where('code', $kodeProdi),
            );
        }

        return $query->orderByDesc('is_active')->first();
    }
}
