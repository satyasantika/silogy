<?php

namespace App\Modules\AI\Exceptions;

use Exception;

class GeminiQuotaExceededException extends Exception
{
    public static function userDailyLimit(int $limit): self
    {
        return new self(
            "Batas harian analisis AI per pengguna ({$limit} permintaan/hari) telah tercapai. Coba lagi besok atau hubungi administrator.",
        );
    }

    public static function unitDailyLimit(string $unitNama, int $limit): self
    {
        return new self(
            "Batas harian analisis AI untuk unit «{$unitNama}» ({$limit} permintaan/hari) telah tercapai.",
        );
    }

    public static function monthlyTokenBudget(int $budget, int $used): self
    {
        return new self(
            "Kuota token Gemini bulan ini habis (terpakai ".number_format($used, 0, ',', '.').' / '
            .number_format($budget, 0, ',', '.').' token). Hubungi administrator untuk penambahan kuota.',
        );
    }
}
