<?php

namespace App\Support\Filament\Concerns;

use Livewire\Component;

/**
 * Matikan partial render Filament (wire:partial) pada komponen Livewire ini;
 * setiap interaksi me-render ulang seluruh halaman, bukan patch DOM sebagian.
 *
 * @phpstan-require-extends Component
 */
trait ForcesFullPageRender
{
    public function bootForcesFullPageRender(): void
    {
        $this->forceRender();
    }
}
