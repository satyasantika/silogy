<?php

namespace App\Modules\AI\Support;

use App\Modules\AI\Models\AnalisisAi;

final class AnalisisAiStatus
{
    public static function labelFor(AnalisisAi $analisis): string
    {
        if ($analisis->safety_blocked) {
            return 'Diblokir Safety';
        }

        return match ($analisis->status) {
            'completed' => 'Selesai',
            'failed' => 'Gagal',
            default => 'Aktif',
        };
    }

    public static function colorFor(AnalisisAi $analisis): string
    {
        if ($analisis->safety_blocked) {
            return 'warning';
        }

        return match ($analisis->status) {
            'completed' => 'success',
            'failed' => 'danger',
            default => 'info',
        };
    }
}
