<?php

namespace App\Support\Filament;

use Illuminate\Support\HtmlString;

/**
 * Membungkus banner identitas (kurikulum/MK) sebagai header kartu,
 * dengan keterangan pelengkap di body — pola /penilaian/input-nilai.
 */
final class SilogyBannerPanel
{
    public static function wrap(
        string $bannerHtml,
        ?string $pelengkap = null,
        ?string $bodyHtml = null,
        bool $marginBottom = true,
    ): HtmlString {
        return new HtmlString(view('filament.partials.silogy-banner-header-panel', [
            'banner' => $bannerHtml,
            'pelengkap' => $pelengkap,
            'bodyHtml' => $bodyHtml,
            'marginBottom' => $marginBottom,
        ])->render());
    }
}
