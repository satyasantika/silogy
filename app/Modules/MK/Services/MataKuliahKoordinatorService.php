<?php

namespace App\Modules\MK\Services;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Support\MkTerpilih;
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

    public static function penawaranPadaKurikulum(Mk $mk, Kurikulum $kurikulum): ?MkUnit
    {
        $mk->loadMissing('mkUnits');

        return $mk->mkUnits
            ->first(fn (MkUnit $unit): bool => $unit->kurikulum_id === $kurikulum->id
                && $unit->is_active);
    }

    public static function labelPenawaranPadaKurikulum(Mk $mk, Kurikulum $kurikulum): string
    {
        $kode = static::penawaranPadaKurikulum($mk, $kurikulum)?->kode;

        return filled($kode) ? (string) $kode : '—';
    }

    public static function isMkSedangDikerjakan(Mk $mk): bool
    {
        return MkTerpilih::currentId() === $mk->id;
    }

    /**
     * Header card: kode penawaran kiri, pill SKS kanan (sejajar pola kurikulum).
     */
    public static function headerCardHtml(Mk $mk, ?Kurikulum $kurikulum): HtmlString
    {
        $kode = $kurikulum instanceof Kurikulum
            ? static::labelPenawaranPadaKurikulum($mk, $kurikulum)
            : '—';
        $sks = (int) $mk->total_sks;

        return new HtmlString(
            '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;">'
            .'<div style="min-width:0;display:flex;align-items:baseline;gap:8px;flex-wrap:wrap;">'
            .'<span style="font-weight:700;font-size:14px;color:inherit;">'.e($kode).'</span>'
            .'</div>'
            .'<span style="flex-shrink:0;display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;'
            .'font-size:11px;font-weight:600;line-height:1.4;background:#eff6ff;color:#1d4ed8;'
            .'border:1px solid #bfdbfe;">'.e((string) $sks).' SKS</span>'
            .'</div>'
        );
    }

    /**
     * Meta: baris semester sendiri, lalu badge menu sejajar kiri di bawahnya.
     */
    public static function metaPenawaranHtml(Mk $mk, ?Kurikulum $kurikulum, User $user): HtmlString
    {
        $penawaran = $kurikulum instanceof Kurikulum
            ? static::penawaranPadaKurikulum($mk, $kurikulum)
            : null;
        $semesterKe = $penawaran?->semester_ke;
        $semesterLabel = $semesterKe !== null
            ? 'Semester ke-'.$semesterKe
            : 'Semester —';

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;align-items:stretch;gap:6px;width:100%;margin:0;padding:0;">'
            .'<div style="font-size:12px;line-height:1.4;color:#6b7280;margin:0;padding:0;">'
            .e($semesterLabel)
            .'</div>'
            .static::ketersediaanPenilaianHtml($mk, $user)->toHtml()
            .'</div>'
        );
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
                    .'onclick="event.stopPropagation()" '
                    .'style="display:inline-flex;align-items:center;padding:3px 8px;'
                    .'border-radius:6px;font-size:11px;font-weight:600;line-height:1.4;text-decoration:none;'
                    .'background:'.$background.';color:'.$color.';border:1px solid '.$border.';">'
                    .e($meta['label']).' · '.e($status)
                    .'</a>';
            })
            ->implode('');

        return new HtmlString(
            '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin:0;padding:0;">'
            .$badges
            .'</div>'
        );
    }
}
