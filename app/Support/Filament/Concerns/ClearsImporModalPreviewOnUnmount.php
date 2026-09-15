<?php

namespace App\Support\Filament\Concerns;

/**
 * Kosongkan state pratinjau impor saat modal aksi ditutup (Cancel, ESC,
 * klik di luar, atau setelah submit) — properti Livewire seperti
 * $importMassalRowsLive / $pakaiUlangSumberLive bertahan antar
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

        if (property_exists($this, 'pakaiUlangSumberLive')) {
            $this->pakaiUlangSumberLive = null;
        }

        if (property_exists($this, 'pakaiUlangBarisCache')) {
            $this->pakaiUlangBarisCache = [];
        }

        if (property_exists($this, 'adaptasiSumberLive')) {
            $this->adaptasiSumberLive = [];
        }

        if (property_exists($this, 'adaptasiBarisCache')) {
            $this->adaptasiBarisCache = [];
        }
    }
}
