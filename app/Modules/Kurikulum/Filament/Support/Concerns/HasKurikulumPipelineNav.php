<?php

namespace App\Modules\Kurikulum\Filament\Support\Concerns;

use App\Modules\Kurikulum\Support\KurikulumPipeline;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Schemas\Components\Actions;
use Filament\Support\Enums\Alignment;

trait HasKurikulumPipelineNav
{
    private ?array $kurikulumPipelineNavMemo = null;

    abstract protected function kurikulumPipelineStepKey(): string;

    /**
     * Memoized per request. The pipeline nav bar is built once, early in the
     * Livewire lifecycle (schema caching in InteractsWithSchemas), so any
     * decision baked eagerly into which actions exist would go stale after a
     * same-request mutation (e.g. bulk import). Individual `label()`/`url()`/
     * `visible()` closures on each Action, by contrast, are re-evaluated at
     * actual render time — so the "which action is visible" decision must
     * live inside those closures, not in which items get added to the array.
     */
    private function kurikulumPipelineNavMemo(): array
    {
        return $this->kurikulumPipelineNavMemo ??= KurikulumPipeline::navFor($this->kurikulumPipelineStepKey());
    }

    /**
     * @return array<int, Actions>
     */
    protected function kurikulumPipelineNavComponents(): array
    {
        $backAction = Action::make('kurikulumPipelineBack')
            ->label(fn (): string => '« '.($this->kurikulumPipelineNavMemo()['prev']['label'] ?? ''))
            ->url(fn (): string => $this->kurikulumPipelineNavMemo()['prev']['url'] ?? '#')
            ->visible(fn (): bool => $this->kurikulumPipelineNavMemo()['prev'] !== null)
            ->color('gray')
            ->button();

        $nextAction = Action::make('kurikulumPipelineNext')
            ->label(fn (): string => ($this->kurikulumPipelineNavMemo()['next']['label'] ?? '').' »')
            ->url(fn (): string => $this->kurikulumPipelineNavMemo()['next']['url'] ?? '#')
            ->visible(fn (): bool => $this->kurikulumPipelineNavMemo()['next'] !== null)
            ->color('primary')
            ->button();

        // Isi grup (link mana yang berlaku) hanya bergantung pada tipe unit
        // & role user — stabil dalam satu request, aman dibangun eager
        // (ActionGroup::make() Filament cuma menerima array, bukan closure).
        // Yang perlu reaktif (tampil/tidak setelah data berubah di request
        // yang sama, mis. import massal) digerbangi lewat ->visible().
        $stepKey = $this->kurikulumPipelineStepKey();
        $finishGroup = ActionGroup::make(
            collect(KurikulumPipeline::finishLinksFor($stepKey))
                ->map(fn (array $destination, int $index): Action => Action::make('kurikulumPipelineFinish'.$index)
                    ->label($destination['label'])
                    ->url($destination['url']))
                ->all()
        )
            ->label('Interaksi & Pelaporan »')
            ->color('primary')
            ->button()
            ->visible(fn (): bool => KurikulumPipeline::isFinishStepReady($stepKey));

        return [
            Actions::make([$backAction, $nextAction, $finishGroup])
                ->alignment(Alignment::Between)
                ->key('kurikulum-pipeline-nav'),
        ];
    }
}
