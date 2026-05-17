<?php

namespace App\Modules\Kurikulum\Listeners;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\StateTransition;
use Spatie\ModelStates\Events\StateChanged;

class LogStateTransition
{
    public function handle(StateChanged $event): void
    {
        if (! $event->model instanceof Kurikulum) {
            return;
        }

        StateTransition::query()->create([
            'model_type' => Kurikulum::class,
            'model_id' => $event->model->id,
            'from_state' => $event->initialState?->getValue(),
            'to_state' => $event->finalState->getValue(),
            'actor_id' => auth()->id(),
        ]);
    }
}
