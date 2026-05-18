<?php

namespace App\Modules\Kalkulasi\Support;

use App\Modules\Kalkulasi\Models\HasilCplUnit;
use Illuminate\Support\Facades\Cache;

class DashboardCplCache
{
    public const int TTL_SECONDS = 600;

    public static function chartKey(string $academicUnitId, string $semesterId): string
    {
        return "dashboard:{$academicUnitId}:{$semesterId}:cpl";
    }

    public static function mkUnitTableKey(string $academicUnitId, string $semesterId, string $cplId): string
    {
        return "dashboard:{$academicUnitId}:{$semesterId}:cpl_mk:{$cplId}";
    }

    public static function invalidate(string $academicUnitId, string $semesterId): void
    {
        Cache::forget(self::chartKey($academicUnitId, $semesterId));

        $cplIds = HasilCplUnit::query()
            ->where('academic_unit_id', $academicUnitId)
            ->where('semester_id', $semesterId)
            ->pluck('cpl_id');

        foreach ($cplIds as $cplId) {
            Cache::forget(self::mkUnitTableKey($academicUnitId, $semesterId, (string) $cplId));
        }
    }
}
