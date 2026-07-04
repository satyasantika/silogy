<?php

namespace App\Modules\AI\Jobs;

use App\Models\User;
use App\Modules\AI\Builders\AnalisisCplBuilder;
use App\Modules\AI\Models\AnalisisAi;
use App\Modules\AI\Notifications\AnalisisAiGagalNotification;
use App\Modules\AI\Services\GeminiClientService;
use App\Modules\AI\Services\GeminiCostGuard;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunAnalisisAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(
        public string $analisisAiId,
    ) {
        $this->onQueue('ai-analysis');
    }

    public function handle(
        GeminiClientService $gemini,
        AnalisisCplBuilder $builder,
        GeminiCostGuard $costGuard,
    ): void {
        $analisis = AnalisisAi::query()->findOrFail($this->analisisAiId);

        $unit = AcademicUnit::query()->findOrFail($analisis->academic_unit_id);
        $user = User::query()->findOrFail($analisis->dibuat_oleh);

        $costGuard->check($user, $unit, $analisis->id);

        $analisis->update(['status' => 'running']);

        $semester = Semester::query()->findOrFail($analisis->semester_id);

        $payload = $builder
            ->forUnit($unit, $semester)
            ->withType($analisis->jenis)
            ->build();

        $resp = match ($analisis->jenis) {
            'ringkasan_cpl' => $gemini->summarize($payload['prompt'], $payload['context']),
            default => $gemini->generate($payload['prompt'], $payload['context']),
        };

        $analisis->update([
            'status' => 'completed',
            'prompt' => $payload['prompt'],
            'konteks' => $payload['context'],
            'hasil' => $resp->text,
            'model_ai' => $resp->model,
            'token_digunakan' => $resp->total_token_count,
            'durasi_ms' => $resp->latency_ms,
            'finish_reason' => $resp->finish_reason,
            'safety_blocked' => self::isSafetyBlocked($resp->finish_reason),
        ]);
    }

    public function failed(Throwable $e): void
    {
        $analisis = AnalisisAi::query()->find($this->analisisAiId);

        if ($analisis === null) {
            return;
        }

        $analisis->update([
            'status' => 'failed',
            'hasil' => '[ERROR] '.$e->getMessage(),
        ]);

        $pembuat = $analisis->dibuatOleh;

        if ($pembuat !== null) {
            $pembuat->notify(new AnalisisAiGagalNotification($analisis, $e->getMessage()));
        }
    }

    private static function isSafetyBlocked(?string $finishReason): bool
    {
        if ($finishReason === null) {
            return false;
        }

        return in_array($finishReason, [
            'SAFETY',
            'BLOCKLIST',
            'PROHIBITED_CONTENT',
            'IMAGE_SAFETY',
            'IMAGE_PROHIBITED_CONTENT',
        ], true);
    }
}
