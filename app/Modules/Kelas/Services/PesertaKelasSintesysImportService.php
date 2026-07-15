<?php

namespace App\Modules\Kelas\Services;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Validation\ValidationException;

/**
 * Impor peserta dari Sintesys yang dibatasi pada SATU mata kuliah (dipilih
 * Koordinator MK lewat halaman "Mahasiswa"), berbeda dari
 * {@see KelasMkJsonImportService} yang menarik seluruh prodi sekaligus.
 * Baris pada payload yang kode_mk-nya tidak cocok dengan MK terpilih
 * diabaikan sebagai jaga-jaga bila Sintesys mengembalikan data di luar
 * cakupan permintaan.
 */
class PesertaKelasSintesysImportService
{
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
    public function import(array $payload, MkUnit $mkUnit, Semester $semester): array
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

            if ($kodeMk !== '' && strcasecmp($kodeMk, $mkUnit->kode) !== 0) {
                $errors[] = "Data ke-{$baris}: kode_mk \"{$kodeMk}\" bukan mata kuliah yang terpilih (\"{$mkUnit->kode}\"), dilewati.";

                continue;
            }

            $kodeKelas = trim((string) ($item['kelas'] ?? ''));

            if ($kodeKelas === '') {
                $errors[] = "Data ke-{$baris}: kelas kosong.";

                continue;
            }

            $dosenId = null;
            $nidn = trim((string) ($item['dosen_pengampu']['nidn'] ?? ''));

            if ($nidn !== '') {
                $dosen = User::query()->where('nidn', $nidn)->first();

                if ($dosen instanceof User) {
                    $dosenId = $dosen->id;
                } else {
                    $errors[] = "Data ke-{$baris} ({$mkUnit->kode}/{$kodeKelas}): dosen dengan NIDN \"{$nidn}\" tidak ditemukan.";
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

            $peserta = $item['peserta'] ?? [];

            if (! is_array($peserta)) {
                continue;
            }

            foreach ($peserta as $p) {
                if (! is_array($p)) {
                    continue;
                }

                $nim = trim((string) ($p['nim'] ?? $p['npm'] ?? ''));

                if ($nim === '') {
                    continue;
                }

                $mahasiswa = Mahasiswa::query()
                    ->where('nim', $nim)
                    ->where('academic_unit_id', $mkUnit->academic_unit_id)
                    ->first();

                if (! $mahasiswa instanceof Mahasiswa) {
                    if (Mahasiswa::query()->where('nim', $nim)->exists()) {
                        $errors[] = "Data ke-{$baris} ({$mkUnit->kode}/{$kodeKelas}): mahasiswa NIM \"{$nim}\" sudah terdaftar pada prodi lain.";

                        continue;
                    }

                    $namaMahasiswa = trim((string) ($p['nama'] ?? $p['nama_mahasiswa'] ?? ''));

                    $mahasiswa = Mahasiswa::query()->create([
                        'nim' => $nim,
                        'nama' => $namaMahasiswa !== '' ? $namaMahasiswa : null,
                        'academic_unit_id' => $mkUnit->academic_unit_id,
                    ]);

                    $mahasiswaDibuat++;
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
}
