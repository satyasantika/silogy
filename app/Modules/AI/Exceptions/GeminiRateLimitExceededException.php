<?php

namespace App\Modules\AI\Exceptions;

use Exception;

class GeminiRateLimitExceededException extends Exception
{
    public static function forUser(string $userId, int $limit): self
    {
        return new self(
            "Batas permintaan analisis AI ({$limit}/hari) untuk pengguna {$userId} telah tercapai.",
        );
    }
}
