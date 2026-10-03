<?php

namespace App\Modules\Simulasi\Exceptions;

use RuntimeException;

class KapasitasSandboxPenuhException extends RuntimeException
{
    public static function buat(int $maks): self
    {
        return new self("Jumlah sandbox simulasi sudah mencapai batas ($maks). Hapus yang tidak terpakai atau naikkan SIMULASI_MAKS_SANDBOX.");
    }
}
