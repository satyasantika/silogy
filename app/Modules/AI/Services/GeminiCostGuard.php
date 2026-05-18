<?php

namespace App\Modules\AI\Services;

use App\Models\User;
use App\Modules\AI\Exceptions\GeminiQuotaExceededException;
use App\Modules\AI\Models\AnalisisAi;
use App\Modules\Institusi\Models\AcademicUnit;
use Illuminate\Support\Carbon;

class GeminiCostGuard
{
    public const MAX_REQUESTS_PER_USER_PER_DAY = 5;

    public const MAX_REQUESTS_PER_UNIT_PER_DAY = 50;

    public const DEFAULT_MONTHLY_TOKEN_BUDGET = 5_000_000;

    public function check(User $user, AcademicUnit $unit, ?string $excludeAnalisisId = null): void
    {
        $this->assertUserDailyQuota($user, $excludeAnalisisId);
        $this->assertUnitDailyQuota($unit, $excludeAnalisisId);
        $this->assertMonthlyTokenBudget();
    }

    private function assertUserDailyQuota(User $user, ?string $excludeAnalisisId): void
    {
        $count = $this->dailyRequestQuery($excludeAnalisisId)
            ->where('dibuat_oleh', $user->id)
            ->count();

        if ($count >= self::MAX_REQUESTS_PER_USER_PER_DAY) {
            throw GeminiQuotaExceededException::userDailyLimit(self::MAX_REQUESTS_PER_USER_PER_DAY);
        }
    }

    private function assertUnitDailyQuota(AcademicUnit $unit, ?string $excludeAnalisisId): void
    {
        $count = $this->dailyRequestQuery($excludeAnalisisId)
            ->where('academic_unit_id', $unit->id)
            ->count();

        if ($count >= self::MAX_REQUESTS_PER_UNIT_PER_DAY) {
            throw GeminiQuotaExceededException::unitDailyLimit(
                $unit->nama,
                self::MAX_REQUESTS_PER_UNIT_PER_DAY,
            );
        }
    }

    private function assertMonthlyTokenBudget(): void
    {
        $budget = $this->monthlyTokenBudget();
        $used = $this->monthlyTokenUsage();

        if ($used >= $budget) {
            throw GeminiQuotaExceededException::monthlyTokenBudget($budget, $used);
        }
    }

    private function dailyRequestQuery(?string $excludeAnalisisId)
    {
        return AnalisisAi::query()
            ->whereDate('created_at', Carbon::today())
            ->when(
                $excludeAnalisisId !== null,
                fn ($query) => $query->where('id', '!=', $excludeAnalisisId),
            );
    }

    public function monthlyTokenBudget(): int
    {
        return (int) config('services.gemini.monthly_token_budget', self::DEFAULT_MONTHLY_TOKEN_BUDGET);
    }

    public function monthlyTokenUsage(): int
    {
        $now = Carbon::now();

        return (int) AnalisisAi::query()
            ->whereYear('created_at', $now->year)
            ->whereMonth('created_at', $now->month)
            ->whereNotNull('token_digunakan')
            ->sum('token_digunakan');
    }
}
