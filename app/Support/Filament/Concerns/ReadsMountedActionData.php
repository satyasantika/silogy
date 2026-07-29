<?php

namespace App\Support\Filament\Concerns;

/**
 * Membaca state form modal aksi yang sedang ter-mount.
 *
 * Dibutuhkan saat menilai sesuatu di LUAR schema — mis. visibilitas tombol
 * submit modal — karena Get() hanya tersedia di dalam schema. Field modal
 * terikat ke mountedActions.{i}.data.*, jadi nilai di sana selalu yang
 * terkini walau properti live komponen belum tersinkron.
 */
trait ReadsMountedActionData
{
    /**
     * @return array<string, mixed>
     */
    protected function mountedActionDataTerkini(): array
    {
        if (! property_exists($this, 'mountedActions')) {
            return [];
        }

        $mounted = $this->mountedActions ?? [];

        if ($mounted === []) {
            return [];
        }

        return $mounted[array_key_last($mounted)]['data'] ?? [];
    }
}
