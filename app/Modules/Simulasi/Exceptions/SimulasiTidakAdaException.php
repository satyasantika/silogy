<?php

namespace App\Modules\Simulasi\Exceptions;

use RuntimeException;

class SimulasiTidakAdaException extends RuntimeException
{
    public static function buat(): self
    {
        return new self('Belum ada data simulasi yang bisa dihapus.');
    }
}
