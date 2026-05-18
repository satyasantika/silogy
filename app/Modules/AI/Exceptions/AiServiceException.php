<?php

namespace App\Modules\AI\Exceptions;

use Exception;
use Throwable;

class AiServiceException extends Exception
{
    public static function from(Throwable $previous): self
    {
        return new self(
            message: 'Permintaan Gemini API gagal: '.$previous->getMessage(),
            code: (int) $previous->getCode(),
            previous: $previous,
        );
    }
}
