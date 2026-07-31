<?php

namespace App\Modules\MK\Filament\Support\Concerns;

use App\Modules\MK\Support\MkPipeline;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Support\Enums\Alignment;

/**
 * Tombol « Back / Next » di bawah daftar, pola sama HasKurikulumPipelineNav.
 */
trait HasMkPipelineNav
{
    abstract protected function mkPipelineStepKey(): string;

    /**
     * Keputusan visible/label/url di-closure + navFor dipanggil ulang tiap
     * render agar next muncul setelah mutasi data dalam request yang sama
     * (impor massal / create), tanpa memo stale.
     *
     * @return array<int, Actions>
     */
    protected function mkPipelineNavComponents(): array
    {
        $stepKey = $this->mkPipelineStepKey();

        $backAction = Action::make('mkPipelineBack')
            ->label(fn (): string => '« '.(MkPipeline::navFor($stepKey)['prev']['label'] ?? ''))
            ->url(fn (): string => MkPipeline::navFor($stepKey)['prev']['url'] ?? '#')
            ->visible(fn (): bool => MkPipeline::navFor($stepKey)['prev'] !== null)
            ->color('gray')
            ->button();

        $nextAction = Action::make('mkPipelineNext')
            ->label(fn (): string => (MkPipeline::navFor($stepKey)['next']['label'] ?? '').' »')
            ->url(fn (): string => MkPipeline::navFor($stepKey)['next']['url'] ?? '#')
            ->visible(fn (): bool => MkPipeline::navFor($stepKey)['next'] !== null)
            ->color('primary')
            ->button();

        return [
            Actions::make([$backAction, $nextAction])
                ->alignment(Alignment::Between)
                ->key('mk-pipeline-nav'),
        ];
    }
}
