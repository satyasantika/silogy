<?php

namespace App\Support\Filament\Concerns;

/**
 * Matikan partial render Filament (wire:partial) pada komponen Livewire ini;
 * setiap interaksi me-render ulang seluruh halaman, bukan patch DOM sebagian.
 */
trait ForcesFullPageRender
{
    public function bootForcesFullPageRender(): void
    {
        if (method_exists($this, 'forceRender')) {
            $this->forceRender();
        }
    }
}
