<?php

namespace App\Http\Controllers;

use App\Http\Support\JsonEnvelope;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $data = [
            'db' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'disk_free' => $this->checkDiskFree(),
        ];

        $sehat = $data['db'] === 'ok'
            && $data['redis'] === 'ok'
            && $this->diskCukup($data['disk_free']);

        if (! $sehat) {
            return JsonEnvelope::error(
                'HEALTH_CHECK_FAILED',
                'Satu atau lebih komponen infrastruktur tidak sehat.',
                503,
                $data,
            );
        }

        return JsonEnvelope::success($data);
    }

    protected function checkDatabase(): string
    {
        try {
            DB::connection()->getPdo();

            return 'ok';
        } catch (Throwable) {
            return 'error';
        }
    }

    protected function checkRedis(): string
    {
        try {
            $pong = Redis::connection()->ping();

            if ($pong === true || $pong === 'PONG' || $pong === '+PONG') {
                return 'ok';
            }

            return 'error';
        } catch (Throwable) {
            return 'error';
        }
    }

    protected function checkDiskFree(): string
    {
        $path = storage_path();
        $bytes = @disk_free_space($path);

        if ($bytes === false) {
            return 'unknown';
        }

        $gigabytes = $bytes / 1024 / 1024 / 1024;

        return sprintf('%.2fGB', round($gigabytes, 2));
    }

    protected function diskCukup(string $diskFree): bool
    {
        if (! preg_match('/^([\d.]+)GB$/', $diskFree, $matches)) {
            return false;
        }

        return (float) $matches[1] >= 1.0;
    }
}
