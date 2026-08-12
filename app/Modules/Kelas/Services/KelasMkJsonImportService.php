<?php

namespace App\Modules\Kelas\Services;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Support\PesertaKelasRekonsiliasi;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Mahasiswa\Support\AngkatanDariNim;
use App\Modules\MK\Models\MkUnit;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Impor massal Kelas MK + pendaftaran mahasiswa dari JSON feeder akademik
 * (format SIAKAD: tahun_akademik, kode_prodi, data[] berisi kode_mk/kelas/
 * dosen_pengampu/peserta[]). Field lain pada JSON (nama_mk, sks, nilai,
 * komponen_evaluasi, dst.) sengaja diabaikan — hanya dipakai untuk
 * membuat/menyamakan struktur Kelas MK dan mendaftarkan pesertanya.
 */
class KelasMkJsonImportService
{
    /**
     * Jumlah unit kerja (kelas + peserta) pada payload — dipakai sebagai
     * penyebut progres saat import dijalankan sebagai job asinkron.
     *
     * @param  array<string, mixed>  $payload
     */
    public function hitungTotalUnit(array $payload): int
    {
        $data = $payload['data'] ?? null;

        if (! is_array($data)) {
            return 0;
        }

        $total = 0;

        foreach ($data as $item) {
            if (! is_array($item)) {
                continue;
            }

            $total++;

            $peserta = $item['peserta'] ?? [];

            if (is_array($peserta)) {
                $total += count($peserta);
            }
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, string>|null  $allowedAcademicUnitIds  Batasi prodi yang boleh diimpor (null = tanpa batasan)
     * @param  Closure|null  $onUnitProcessed  Dipanggil setiap satu kelas atau satu peserta selesai diproses (untuk progres job asinkron)
     * @return array{
     *     semester_kode: string,
     *     academic_unit_nama: string,
     *     kelas_dibuat: int,
     *     kelas_diperbarui: int,
     *     peserta_terdaftar: int,
     *     peserta_sudah_terdaftar: int,
     *     peserta_dihapus: int,
     *     mahasiswa_dibuat: int,
     *     errors: list<string>
     * }
     */
    public function import(
        array $payload,
        ?Collection $allowedAcademicUnitIds = null,
        ?Closure $onUnitProcessed = null,
    ): array {
        $tahunAkademik = trim((string) ($payload['tahun_akademik'] ?? ''));
        $kodeProdi = trim((string) ($payload['kode_prodi'] ?? ''));
        $data = $payload['data'] ?? null;

        if ($tahunAkademik === '' || $kodeProdi === '' || ! is_array($data)) {
            throw ValidationException::withMessages([
                'sumber' => 'Struktur JSON tidak valid: wajib ada "tahun_akademik", "kode_prodi", dan "data" (array).',
            ]);
        }

        $semester = Semester::query()->where('kode', $tahunAkademik)->first();

        if (! $semester instanceof Semester) {
            throw ValidationException::withMessages([
                'sumber' => "Semester dengan kode \"{$tahunAkademik}\" tidak ditemukan.",
            ]);
        }

        $academicUnit = AcademicUnit::query()->where('code', $kodeProdi)->first();

        if (! $academicUnit instanceof AcademicUnit) {
            throw ValidationException::withMessages([
                'sumber' => "Program studi dengan kode \"{$kodeProdi}\" tidak ditemukan.",
            ]);
        }

        if ($allowedAcademicUnitIds !== null && ! $allowedAcademicUnitIds->contains($academicUnit->id)) {
            throw ValidationException::withMessages([
                'sumber' => "Anda tidak memiliki akses untuk mengelola kelas pada prodi \"{$kodeProdi}\".",
            ]);
        }

        $kelasDibuat = 0;
        $kelasDiperbarui = 0;
        $pesertaTerdaftar = 0;
        $pesertaSudahTerdaftar = 0;
        $pesertaDihapus = 0;
        $mahasiswaDibuat = 0;
        $errors = [];

        // Diproses per baris (bukan satu transaksi besar) agar toleran
        // terhadap baris bermasalah dan agar progres job asinkron benar-benar
        // terlihat berjalan (bukan baru muncul sekaligus di akhir).
        foreach ($data as $index => $item) {
            $baris = $index + 1;

            if (! is_array($item)) {
                $errors[] = "Data ke-{$baris}: format tidak valid.";

                continue;
            }

            $kodeMk = trim((string) ($item['kode_mk'] ?? ''));
            $kodeKelas = trim((string) ($item['kelas'] ?? ''));

            if ($kodeMk === '' || $kodeKelas === '') {
                $errors[] = "Data ke-{$baris}: kode_mk atau kelas kosong.";
                $onUnitProcessed?->__invoke();

                continue;
            }

            $mkUnit = MkUnit::query()
                ->with('mk')
                ->where('academic_unit_id', $academicUnit->id)
                ->where('kode', $kodeMk)
                ->first();

            if (! $mkUnit instanceof MkUnit) {
                $errors[] = "Data ke-{$baris}: MK dengan kode \"{$kodeMk}\" tidak ditemukan pada prodi \"{$kodeProdi}\".";
                $onUnitProcessed?->__invoke();

                continue;
            }

            $dosenId = null;
            $nidn = trim((string) ($item['dosen_pengampu']['nidn'] ?? ''));

            if ($nidn !== '') {
                $dosen = User::query()->where('nidn', $nidn)->first();

                if ($dosen instanceof User) {
                    $dosenId = $dosen->id;
                } else {
                    $errors[] = "Data ke-{$baris} ({$kodeMk}/{$kodeKelas}): dosen dengan NIDN \"{$nidn}\" tidak ditemukan.";
                }
            }

            $kelas = KelasMk::query()
                ->where('mk_unit_id', $mkUnit->id)
                ->where('semester_id', $semester->id)
                ->where('kode_kelas', $kodeKelas)
                ->first();

            if ($kelas instanceof KelasMk) {
                $kelasDiperbarui++;

                if ($dosenId !== null) {
                    $kelas->update(['dosen_pengampu_id' => $dosenId]);
                }
            } else {
                $kelas = KelasMk::query()->create([
                    'mk_unit_id' => $mkUnit->id,
                    'semester_id' => $semester->id,
                    'kode_kelas' => $kodeKelas,
                    'dosen_pengampu_id' => $dosenId,
                    'koordinator_mk_id' => $mkUnit->mk?->koordinator_mk_id,
                ]);
                $kelasDibuat++;
            }

            $onUnitProcessed?->__invoke();

            $peserta = $item['peserta'] ?? [];

            if (! is_array($peserta)) {
                continue;
            }

            $mahasiswaIds = [];

            foreach ($peserta as $p) {
                if (! is_array($p)) {
                    $onUnitProcessed?->__invoke();

                    continue;
                }

                $npm = trim((string) ($p['npm'] ?? ''));

                if ($npm === '') {
                    $onUnitProcessed?->__invoke();

                    continue;
                }

                $mahasiswa = Mahasiswa::query()
                    ->where('nim', $npm)
                    ->where('academic_unit_id', $academicUnit->id)
                    ->first();

                if (! $mahasiswa instanceof Mahasiswa) {
                    if (Mahasiswa::query()->where('nim', $npm)->exists()) {
                        $errors[] = "Data ke-{$baris} ({$kodeMk}/{$kodeKelas}): mahasiswa NPM \"{$npm}\" sudah terdaftar pada prodi lain.";
                        $onUnitProcessed?->__invoke();

                        continue;
                    }

                    $namaMahasiswa = trim((string) ($p['nama'] ?? $p['nama_mahasiswa'] ?? ''));

                    $mahasiswa = Mahasiswa::query()->create([
                        'nim' => $npm,
                        'nama' => $namaMahasiswa !== '' ? $namaMahasiswa : null,
                        'academic_unit_id' => $academicUnit->id,
                        'angkatan' => AngkatanDariNim::dari($npm),
                    ]);

                    $mahasiswaDibuat++;
                } else {
                    AngkatanDariNim::isiBilaKosong($mahasiswa);
                }

                $mahasiswaIds[] = $mahasiswa->id;

                $onUnitProcessed?->__invoke();
            }

            $rekon = PesertaKelasRekonsiliasi::terapkan($kelas, $mahasiswaIds);
            $pesertaTerdaftar += $rekon['terdaftar'];
            $pesertaSudahTerdaftar += $rekon['sudah_terdaftar'];
            $pesertaDihapus += $rekon['dihapus'];
        }

        return [
            'semester_kode' => $semester->kode,
            'academic_unit_nama' => $academicUnit->nama_lengkap,
            'kelas_dibuat' => $kelasDibuat,
            'kelas_diperbarui' => $kelasDiperbarui,
            'peserta_terdaftar' => $pesertaTerdaftar,
            'peserta_sudah_terdaftar' => $pesertaSudahTerdaftar,
            'peserta_dihapus' => $pesertaDihapus,
            'mahasiswa_dibuat' => $mahasiswaDibuat,
            'errors' => $errors,
        ];
    }
}
