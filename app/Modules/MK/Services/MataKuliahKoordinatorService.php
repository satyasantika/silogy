<?php

namespace App\Modules\MK\Services;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use Illuminate\Support\HtmlString;

class MataKuliahKoordinatorService
{
    /**
     * @return array{cpmk: bool, subcpmk: bool, asesmen: bool, mahasiswa: bool}
     */
    public static function ketersediaanPenilaian(Mk $mk, User $user): array
    {
        $hasCpmk = Cpmk::query()->where('mk_id', $mk->id)->exists();

        $hasSubcpmk = Subcpmk::query()
            ->whereHas(
                'mkCpmk.cpmk',
                fn ($query) => $query->where('mk_id', $mk->id),
            )
            ->exists();

        $hasAsesmen = KomponenPenilaian::query()
            ->where('mk_id', $mk->id)
            ->exists();

        $hasMahasiswa = KelasMk::query()
            ->whereHas(
                'mkUnit',
                fn ($query) => $query->where('mk_id', $mk->id),
            )
            ->whereHas('mahasiswas')
            ->exists();

        return [
            'cpmk' => $hasCpmk,
            'subcpmk' => $hasSubcpmk,
            'asesmen' => $hasAsesmen,
            'mahasiswa' => $hasMahasiswa,
        ];
    }

    public static function labelPenawaranPadaKurikulum(Mk $mk, Kurikulum $kurikulum): string
    {
        $mk->loadMissing('mkUnits');

        $kode = $mk->mkUnits
            ->firstWhere('academic_unit_id', $kurikulum->academic_unit_id)
            ?->kode;

        return filled($kode) ? (string) $kode : '—';
    }

    public static function ketersediaanPenilaianHtml(Mk $mk, User $user): HtmlString
    {
        $menu = self::ketersediaanPenilaian($mk, $user);

        $links = [
            'cpmk' => ['label' => 'CPMK', 'menu' => 'cpmk'],
            'subcpmk' => ['label' => 'Sub-CPMK', 'menu' => 'subcpmk'],
            'asesmen' => ['label' => 'Asesmen', 'menu' => 'asesmen'],
            'mahasiswa' => ['label' => 'Mahasiswa', 'menu' => 'mahasiswa'],
        ];

        $badges = collect($menu)
            ->map(function (bool $ada, string $key) use ($mk, $links): string {
                $meta = $links[$key] ?? null;

                if ($meta === null) {
                    return '';
                }

                $url = route('silogy.mk-navigasi', [
                    'mk' => $mk->id,
                    'menu' => $meta['menu'],
                ]);

                $background = $ada ? '#dcfce7' : '#f3f4f6';
                $color = $ada ? '#166534' : '#6b7280';
                $border = $ada ? '#86efac' : '#d1d5db';
                $status = $ada ? 'Ada' : 'Belum';

                return '<a href="'.e($url).'" '
                    .'style="display:inline-flex;align-items:center;padding:3px 8px;'
                    .'border-radius:6px;font-size:11px;font-weight:600;line-height:1.4;text-decoration:none;'
                    .'background:'.$background.';color:'.$color.';border:1px solid '.$border.';">'
                    .e($meta['label']).' · '.e($status)
                    .'</a>';
            })
            ->implode('');

        return new HtmlString(
            '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin-top:4px;padding-top:6px;">'
            .$badges
            .'</div>'
        );
    }
}
