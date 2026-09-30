<?php

namespace App\Modules\Simulasi\Exceptions;

use RuntimeException;

/**
 * Simulasi MEMINJAM semester, tidak pernah membuatnya: seluruh FK semester
 * bersifat RESTRICT, jadi baris semester yang terlanjur dibuat simulasi tidak
 * akan pernah bisa dihapus kembali dengan aman.
 */
class SemesterAktifTidakAdaException extends RuntimeException
{
    public static function buat(): self
    {
        return new self(
            'Tidak ada semester aktif. Aktifkan satu semester lewat menu Semester '
            .'sebelum membuat data simulasi.'
        );
    }
}
