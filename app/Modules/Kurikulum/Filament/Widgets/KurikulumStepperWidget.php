<?php

namespace App\Modules\Kurikulum\Filament\Widgets;

use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Models\Kurikulum;
use Filament\Widgets\Widget;

class KurikulumStepperWidget extends Widget
{
    public Kurikulum $record;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.modules.kurikulum.widgets.kurikulum-stepper';

    /**
     * @return list<array{key: string, label: string, status: string}>
     */
    public function getSteps(): array
    {
        $this->record->loadMissing('academicUnit');

        $workflow = KurikulumResource::workflowStepsFor($this->record);
        $current = $this->record->state->getValue();
        $currentIndex = array_search($current, $workflow, true);
        $currentIndex = $currentIndex === false ? 0 : $currentIndex;

        $steps = [];

        foreach ($workflow as $index => $stateKey) {
            $stateClass = KurikulumResource::stateClassFor($stateKey);
            $label = KurikulumResource::stateOptions()[$stateKey] ?? $stateKey;

            if ($index < $currentIndex) {
                $status = 'completed';
            } elseif ($index === $currentIndex) {
                $status = 'current';
            } elseif ($index === $currentIndex + 1) {
                $status = $this->record->state->canTransitionTo($stateClass) ? 'next' : 'locked';
            } else {
                $status = 'locked';
            }

            $steps[] = [
                'key' => $stateKey,
                'label' => $label,
                'status' => $status,
            ];
        }

        return $steps;
    }
}
