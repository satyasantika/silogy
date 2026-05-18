<?php

namespace App\Modules\AI\RateLimiters;

use App\Modules\AI\Exceptions\GeminiRateLimitExceededException;
use Illuminate\Support\Facades\RateLimiter;

final class GeminiPerUserPerDay
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 86_400;

    public static function rateLimiterName(): string
    {
        return 'gemini-per-user-per-day';
    }

    public static function keyForUser(string $userId): string
    {
        return self::rateLimiterName().':'.$userId;
    }

    public static function tooManyAttempts(string $userId): bool
    {
        return RateLimiter::tooManyAttempts(self::keyForUser($userId), self::MAX_ATTEMPTS);
    }

    public static function remaining(string $userId): int
    {
        return RateLimiter::remaining(self::keyForUser($userId), self::MAX_ATTEMPTS);
    }

    public static function hit(string $userId): void
    {
        RateLimiter::hit(self::keyForUser($userId), self::DECAY_SECONDS);
    }

    /**
     * @throws GeminiRateLimitExceededException
     */
    public static function ensure(string $userId): void
    {
        if (self::tooManyAttempts($userId)) {
            throw GeminiRateLimitExceededException::forUser($userId, self::MAX_ATTEMPTS);
        }

        self::hit($userId);
    }
}
