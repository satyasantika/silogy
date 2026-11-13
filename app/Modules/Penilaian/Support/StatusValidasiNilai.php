<?php

namespace App\Modules\Penilaian\Support;

use App\Modules\Penilaian\Models\ValidasiNilaiKelas;

enum StatusValidasiNilai: string
{
    case Belum = 'belum';
    case Tervalidasi = 'tervalidasi';
    case PerluUlang = 'perlu_ulang';

    public function label(): string
    {
        return match ($this) {
            self::Belum => 'Belum divalidasi',
            self::Tervalidasi => 'Tervalidasi',
            self::PerluUlang => 'Perlu validasi ulang',
        };
    }

    public static function dari(?ValidasiNilaiKelas $baris): self
    {
        if ($baris === null) {
            return self::Belum;
        }

        return $baris->gugur_at === null ? self::Tervalidasi : self::PerluUlang;
    }
}
