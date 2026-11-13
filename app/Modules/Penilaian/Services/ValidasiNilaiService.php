<?php

namespace App\Modules\Penilaian\Services;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Penilaian\Models\ValidasiNilaiKelas;
use App\Modules\Penilaian\Support\StatusValidasiNilai;
use App\Modules\Simulasi\Support\HanyaBaca;

class ValidasiNilaiService
{
    /** Status dari baris tersimpan saja; tidak menghitung sidik. */
    public function status(KelasMk $kelas): StatusValidasiNilai
    {
        return StatusValidasiNilai::dari($this->baris($kelas));
    }

    public function validasi(KelasMk $kelas, User $validator, string $sidik): ValidasiNilaiKelas
    {
        return ValidasiNilaiKelas::query()->updateOrCreate(
            ['kelas_mk_id' => $kelas->id],
            [
                'validator_id' => $validator->id,
                'divalidasi_at' => now(),
                'sidik' => $sidik,
                'gugur_at' => null,
            ],
        );
    }

    public function batalkan(KelasMk $kelas): void
    {
        $this->baris($kelas)?->delete();
    }

    public function gugurkan(KelasMk $kelas): void
    {
        if (HanyaBaca::aktif()) {
            return;
        }

        $baris = $this->baris($kelas);

        if ($baris !== null && $baris->gugur_at === null) {
            $baris->gugur_at = now();
            $baris->save();
        }
    }

    /**
     * Bandingkan sidik terkini dengan sidik tersimpan; bila beda (atau sidik
     * tak tersedia) validasi digugurkan. Mode hanya-baca tetap mengembalikan
     * PerluUlang tanpa menulis apa pun.
     */
    public function periksa(KelasMk $kelas, ?string $sidikTerkini): StatusValidasiNilai
    {
        $baris = $this->baris($kelas);

        if ($baris !== null && $baris->gugur_at === null
            && ($sidikTerkini === null || $sidikTerkini !== $baris->sidik)) {
            $this->gugurkan($kelas);

            return StatusValidasiNilai::PerluUlang;
        }

        return StatusValidasiNilai::dari($baris);
    }

    private function baris(KelasMk $kelas): ?ValidasiNilaiKelas
    {
        return ValidasiNilaiKelas::query()->where('kelas_mk_id', $kelas->id)->first();
    }
}
