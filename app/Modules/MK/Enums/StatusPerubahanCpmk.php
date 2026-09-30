<?php

namespace App\Modules\MK\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Status usulan perubahan CPMK.
 *
 * Sengaja enum biasa, bukan spatie/laravel-model-states seperti Kurikulum:
 * di sana ada tujuh tahap dengan penjaga berbasis kelengkapan data
 * (KurikulumState::canTransition()), sedangkan di sini hanya satu keputusan
 * manusia dengan tiga akhir. Penjaganya adalah SIAPA yang bertindak — itu
 * ranah policy, bukan state machine. Riwayatnya tetap terekam lewat
 * state_transitions yang memang polimorfik.
 */
enum StatusPerubahanCpmk: string implements HasColor, HasLabel
{
    case Diajukan = 'diajukan';

    case Disetujui = 'disetujui';

    case Ditolak = 'ditolak';

    case Dibatalkan = 'dibatalkan';

    public function getLabel(): string
    {
        return match ($this) {
            self::Diajukan => 'Menunggu persetujuan',
            self::Disetujui => 'Disetujui',
            self::Ditolak => 'Ditolak',
            self::Dibatalkan => 'Dibatalkan',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Diajukan => 'warning',
            self::Disetujui => 'success',
            self::Ditolak => 'danger',
            self::Dibatalkan => 'gray',
        };
    }

    public function terbuka(): bool
    {
        return $this === self::Diajukan;
    }
}
