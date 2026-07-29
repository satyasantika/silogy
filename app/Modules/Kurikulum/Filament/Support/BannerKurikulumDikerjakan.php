<?php

namespace App\Modules\Kurikulum\Filament\Support;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

/**
 * Banner identitas kurikulum yang sedang dikerjakan untuk dipasang di dalam
 * modal aksi massal. Menggantikan select kurikulum: aksi selalu mengikuti
 * kurikulum terpilih di sesi, jadi banner ini yang menegaskan sasarannya.
 */
class BannerKurikulumDikerjakan
{
    public static function placeholder(
        ?string $catatan = null,
        bool $wajibProdi = true,
        string $name = 'banner_kurikulum_dikerjakan',
    ): Placeholder {
        return Placeholder::make($name)
            ->hiddenLabel()
            ->content(fn (): HtmlString => static::html($catatan, $wajibProdi));
    }

    public static function html(?string $catatan = null, bool $wajibProdi = true): HtmlString
    {
        $kurikulum = KurikulumTerpilih::current();
        $bukanProdi = $wajibProdi && $kurikulum !== null && ! ($kurikulum->academicUnit?->isProdi() ?? false);

        return new HtmlString(view('filament.modules.kurikulum.banner-kurikulum-dikerjakan', [
            'kurikulum' => $bukanProdi ? null : $kurikulum,
            'hierarki' => $kurikulum?->academicUnit !== null
                ? KurikulumTerpilih::unitHierarchyLabel($kurikulum->academicUnit)
                : null,
            'catatan' => $catatan,
            'peringatan' => $bukanProdi
                ? 'Kurikulum yang dikerjakan bukan kurikulum prodi.'
                : 'Belum ada kurikulum yang dikerjakan.',
        ])->render());
    }

    /**
     * Kurikulum yang dikerjakan, hanya bila milik prodi — sasaran sah untuk
     * aksi massal penawaran MK.
     */
    public static function kurikulumProdi(): ?Kurikulum
    {
        $kurikulum = KurikulumTerpilih::current();

        return ($kurikulum?->academicUnit?->isProdi() ?? false) ? $kurikulum : null;
    }
}
