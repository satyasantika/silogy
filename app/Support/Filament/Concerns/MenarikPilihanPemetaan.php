<?php

namespace App\Support\Filament\Concerns;

/**
 * Isian pemetaan pada form create (mis. "CPL terpetakan") bukan kolom model:
 * nilainya ditarik dari data form sebelum disimpan, lalu dipakai halaman
 * create setelah barisnya ada untuk membentuk pivot.
 */
trait MenarikPilihanPemetaan
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: list<string>}
     */
    protected function tarikPilihanPemetaan(array $data, string $kunci): array
    {
        $pilihan = array_values(array_unique(array_filter(
            array_map('strval', (array) ($data[$kunci] ?? [])),
        )));

        unset($data[$kunci]);

        return [$data, $pilihan];
    }
}
