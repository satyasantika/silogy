<?php

namespace App\Modules\AI\Filament\Widgets;

use App\Models\User;
use App\Modules\AI\Exceptions\GeminiQuotaExceededException;
use App\Modules\AI\Jobs\RunAnalisisAiJob;
use App\Modules\AI\Models\AnalisisAi;
use App\Modules\AI\Services\GeminiCostGuard;
use App\Modules\AI\Support\AnalisisAiStatus;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use Filament\Notifications\Notification;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;
use Illuminate\Support\Str;

class AiInsightWidget extends Widget
{
    use CanAccessDashboardWidgets;
    use InteractsWithPageFilters;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected string $view = 'filament.modules.ai.widgets.ai-insight';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->can('minta_analisis_ai')
            && static::canViewDashboardWidgets();
    }

    public function getLatestAnalisis(): ?AnalisisAi
    {
        $academicUnitId = $this->pageFilters['academic_unit_id'] ?? null;
        $semesterId = $this->pageFilters['semester_id'] ?? null;

        if ($academicUnitId === null || $semesterId === null) {
            return null;
        }

        return AnalisisAi::query()
            ->with(['academicUnit', 'semester'])
            ->where('academic_unit_id', $academicUnitId)
            ->where('semester_id', $semesterId)
            ->where('status', 'completed')
            ->whereNotNull('hasil')
            ->latest()
            ->first();
    }

    public function hasAnalisisForActiveSemester(): bool
    {
        return $this->getLatestAnalisis() !== null;
    }

    public function renderedHasil(): ?string
    {
        $analisis = $this->getLatestAnalisis();

        if ($analisis === null || blank($analisis->hasil)) {
            return null;
        }

        return Str::markdown($analisis->hasil);
    }

    public function statusLabel(?AnalisisAi $analisis = null): string
    {
        $analisis ??= $this->getLatestAnalisis();

        if ($analisis === null) {
            return '—';
        }

        return AnalisisAiStatus::labelFor($analisis);
    }

    public function statusColor(?AnalisisAi $analisis = null): string
    {
        $analisis ??= $this->getLatestAnalisis();

        if ($analisis === null) {
            return 'gray';
        }

        return AnalisisAiStatus::colorFor($analisis);
    }

    public function mintaAnalisisSekarang(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $academicUnitId = $this->pageFilters['academic_unit_id'] ?? null;
        $semesterId = $this->pageFilters['semester_id'] ?? null;

        if ($academicUnitId === null || $semesterId === null) {
            Notification::make()
                ->title('Pilih unit dan semester')
                ->body('Atur filter dashboard terlebih dahulu.')
                ->warning()
                ->send();

            return;
        }

        $unit = AcademicUnit::query()->findOrFail($academicUnitId);

        try {
            app(GeminiCostGuard::class)->check($user, $unit);
        } catch (GeminiQuotaExceededException $e) {
            Notification::make()
                ->title('Kuota analisis AI habis')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        $analisis = AnalisisAi::query()->create([
            'academic_unit_id' => $unit->id,
            'semester_id' => $semesterId,
            'jenis' => 'ringkasan_cpl',
            'status' => 'queued',
            'prompt' => '-',
            'dibuat_oleh' => $user->id,
        ]);

        RunAnalisisAiJob::dispatch($analisis->id);

        Notification::make()
            ->title('Analisis dijadwalkan')
            ->body('Analisis dijadwalkan. Cek kembali dalam 30 detik.')
            ->success()
            ->send();
    }
}
