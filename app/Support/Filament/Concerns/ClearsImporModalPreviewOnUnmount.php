<?php

namespace App\Support\Filament\Concerns;

/**
 * Kosongkan state pratinjau impor saat modal aksi ditutup (Cancel, ESC,
 * klik di luar, atau setelah submit) — properti Livewire seperti
 * $importMassalRowsLive / $salinAntarSemesterSumberLive bertahan antar
 * request, jadi tanpa reset di unmount, pratinjau sesi sebelumnya bisa
 * "nempel" sampai mountUsing berikutnya sempat jalan.
 */
trait ClearsImporModalPreviewOnUnmount
{
    public function unmountAction(bool $canCancelParentActions = true): void
    {
        $this->kosongkanPreviewImporModal();

        parent::unmountAction($canCancelParentActions);
    }

    protected function kosongkanPreviewImporModal(): void
    {
        if (property_exists($this, 'importMassalRowsLive')) {
            $this->importMassalRowsLive = '';
        }

        if (property_exists($this, 'importMassalParseCache')) {
            $this->importMassalParseCache = [];
        }

        if (property_exists($this, 'salinAntarSemesterSumberLive')) {
            $this->salinAntarSemesterSumberLive = null;
        }

        if (property_exists($this, 'salinAntarSemesterBarisCache')) {
            $this->salinAntarSemesterBarisCache = [];
        }
    }
}
