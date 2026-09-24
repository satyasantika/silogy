<?php

namespace App\Modules\Simulasi\Exceptions;

use RuntimeException;

class SimulasiSudahAdaException extends RuntimeException
{
    public static function buat(): self
    {
        return new self('Data simulasi sudah ada. Hapus dulu sebelum membuatnya lagi, atau pakai Bangun Ulang.');
    }
}
