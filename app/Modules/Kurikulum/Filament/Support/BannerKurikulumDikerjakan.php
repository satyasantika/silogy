<?php

namespace App\Modules\Kurikulum\Filament\Support;

use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Filament\Forms\Components\Placeholder;
use Illuminate\Support\HtmlString;

/**
 * Banner identitas kurikulum yang sedang dikerjakan untuk dipasang di dalam
 * modal aksi. Menggantikan select kurikulum: aksi selalu mengikuti kurikulum
 * terpilih di sesi, jadi banner ini yang menegaskan sasarannya. Varian
 * berikonnya untuk halaman ada di Livewire KurikulumTerpilihBanner.
 */
class BannerKurikulumDikerjakan
{
    public const VIEW = 'filament.modules.kurikulum.banner-kurikulum-dikerjakan';

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
        $kurikulum = static::kurikulumUntukBanner($wajibProdi);

        return new HtmlString(view(static::VIEW, [
            'kurikulum' => $kurikulum,
            'hierarki' => static::hierarkiUnit(),
            'catatan' => $catatan,
            'peringatan' => static::peringatan($wajibProdi),
            'arahan' => 'Pilih kurikulum lewat banner di halaman daftar, lalu buka kembali modal ini.',
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

    public static function kurikulumUntukBanner(bool $wajibProdi): ?Kurikulum
    {
        return $wajibProdi ? static::kurikulumProdi() : KurikulumTerpilih::current();
    }

    public static function hierarkiUnit(): ?string
    {
        $unit = KurikulumTerpilih::current()?->academicUnit;

        return $unit !== null ? KurikulumTerpilih::unitHierarchyLabel($unit) : null;
    }

    public static function peringatan(bool $wajibProdi): string
    {
        $adaKurikulum = KurikulumTerpilih::current() !== null;

        return $adaKurikulum && $wajibProdi
            ? 'Kurikulum yang dikerjakan bukan kurikulum prodi.'
            : 'Belum ada kurikulum yang dikerjakan.';
    }
}
