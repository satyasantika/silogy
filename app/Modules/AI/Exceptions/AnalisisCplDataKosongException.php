<?php

namespace App\Modules\AI\Exceptions;

use Exception;

class AnalisisCplDataKosongException extends Exception
{
    public static function forUnitSemester(string $unitNama, string $semesterNama): self
    {
        return new self(
            "Tidak ada data hasil_cpl_unit untuk unit «{$unitNama}» pada semester «{$semesterNama}».",
        );
    }
}
