<?php

namespace App\Modules\Kurikulum\Transitions;

use App\Modules\Kurikulum\States\KurikulumState;
use Spatie\ModelStates\DefaultTransition;

class KurikulumTransition extends DefaultTransition
{
    public function canTransition(): bool
    {
        /** @var KurikulumState $currentState */
        $currentState = $this->model->{$this->field};

        $targetClass = $this->newState::class;

        if (! in_array($targetClass, $currentState->transitionTargets(), true)) {
            return false;
        }

        return $currentState->canTransition($targetClass);
    }
}
