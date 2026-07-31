<?php

namespace App\Modules\MK\Services;

use App\Models\User;
use App\Modules\Institusi\Filament\Resources\AcademicUnitResource;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
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

    /**
     * Penawaran untuk ditampilkan di card: utamakan kurikulum terpilih
     * (aktif), lalu penawaran aktif lain, lalu penawaran nonaktif bila ada.
     * MK tanpa penawaran tetap tampil (meta kosong / placeholder).
     */
    public static function penawaranUntukCard(Mk $mk): ?MkUnit
    {
        $mk->loadMissing(['mkUnits.kurikulum.academicUnit']);

        if ($mk->mkUnits->isEmpty()) {
            return null;
        }

        $terpilih = KurikulumTerpilih::current();

        if ($terpilih instanceof Kurikulum) {
            $padaTerpilihAktif = $mk->mkUnits->first(
                fn (MkUnit $unit): bool => $unit->kurikulum_id === $terpilih->id && $unit->is_active,
            );

            if ($padaTerpilihAktif instanceof MkUnit) {
                return $padaTerpilihAktif;
            }
        }

        $aktif = $mk->mkUnits
            ->filter(fn (MkUnit $unit): bool => $unit->is_active)
            ->sortBy(fn (MkUnit $unit): int => (int) ($unit->semester_ke ?? 99))
            ->first();

        if ($aktif instanceof MkUnit) {
            return $aktif;
        }

        return $mk->mkUnits
            ->sortBy(fn (MkUnit $unit): int => (int) ($unit->semester_ke ?? 99))
            ->first();
    }

    public static function labelPenawaranPadaKurikulum(Mk $mk, Kurikulum $kurikulum): string
    {
        $kode = static::penawaranPadaKurikulum($mk, $kurikulum)?->kode;

        return filled($kode) ? (string) $kode : '—';
    }

    public static function labelKurikulumPadaCard(Mk $mk): string
    {
        $kurikulum = static::kurikulumUntukCard($mk);

        if (! $kurikulum instanceof Kurikulum) {
            return 'Kurikulum —';
        }

        $tahun = filled($kurikulum->tahun) ? ' · '.$kurikulum->tahun : '';

        return $kurikulum->nama.$tahun;
    }

    /**
     * Unit akademik pada card: utamakan unit kurikulum penawaran/pemilik,
     * lalu unit pemilik MK.
     */
    public static function unitUntukCard(Mk $mk): ?AcademicUnit
    {
        $kurikulum = static::kurikulumUntukCard($mk);

        if ($kurikulum instanceof Kurikulum) {
            $kurikulum->loadMissing('academicUnit');

            if ($kurikulum->academicUnit instanceof AcademicUnit) {
                return $kurikulum->academicUnit;
            }
        }

        $mk->loadMissing('academicUnit');

        return $mk->academicUnit;
    }

    /**
     * Baris unit pada card koordinator.
     * Prodi/jurusan: "Program Studi · …" / "Jurusan · …".
     * Fakultas/universitas: nama unit saja (tanpa label jenis).
     *
     * @return array{label: string|null, nama: string}
     */
    public static function unitBarisUntukCard(Mk $mk): array
    {
        $unit = static::unitUntukCard($mk);

        if (! $unit instanceof AcademicUnit) {
            return ['label' => null, 'nama' => '—'];
        }

        if (in_array($unit->type, ['study_program', 'department'], true)) {
            [$typeLabel, $nama] = AcademicUnitResource::jenisDanNamaUntukCard($unit);

            return ['label' => $typeLabel, 'nama' => $nama];
        }

        $nama = trim((string) ($unit->nama_lengkap ?: $unit->nama));

        return ['label' => null, 'nama' => $nama !== '' ? $nama : '—'];
    }

    public static function labelUnitPadaCard(Mk $mk): string
    {
        $baris = static::unitBarisUntukCard($mk);

        if ($baris['label'] === null) {
            return $baris['nama'];
        }

        return $baris['label'].' · '.$baris['nama'];
    }

    public static function isMkSedangDikerjakan(Mk $mk): bool
    {
        // Bandingkan session langsung agar status card sinkron dengan aksi Kerjakan.
        $id = session()->get(MkTerpilih::SESSION_KEY);

        return filled($id) && (string) $id === (string) $mk->id;
    }

    /**
     * Judul card: nama MK + pill SKS sejajar (satu baris).
     */
    public static function judulCardHtml(Mk $mk): HtmlString
    {
        $sks = (int) $mk->total_sks;

        return new HtmlString(
            '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;width:100%;">'
            .'<span style="min-width:0;font-weight:500;font-size:14px;line-height:1.4;color:inherit;">'
            .e($mk->nama)
            .'</span>'
            .'<span style="flex-shrink:0;display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;'
            .'font-size:11px;font-weight:600;line-height:1.4;background:#eff6ff;color:#1d4ed8;'
            .'border:1px solid #bfdbfe;">'.e((string) $sks).' SKS</span>'
            .'</div>'
        );
    }

    /**
     * Meta: kurikulum, lalu baris unit, lalu badge menu.
     */
    public static function metaPenawaranHtml(Mk $mk, User $user): HtmlString
    {
        $kurikulumLabel = static::labelKurikulumPadaCard($mk);
        $baris = static::unitBarisUntukCard($mk);
        $unitHtml = $baris['label'] === null
            ? e($baris['nama'])
            : '<span style="font-weight:600;color:#374151;">'.e($baris['label']).'</span>'
                .' · '.e($baris['nama']);

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;align-items:stretch;gap:6px;width:100%;margin:0;padding:0;">'
            .'<div style="font-size:12px;line-height:1.4;font-weight:600;color:#374151;margin:0;padding:0;">'
            .e($kurikulumLabel)
            .'</div>'
            .'<div style="font-size:12px;line-height:1.4;color:#6b7280;margin:0;padding:0;">'
            .$unitHtml
            .'</div>'
            .static::ketersediaanPenilaianHtml($mk, $user)->toHtml()
            .'</div>'
        );
    }

    protected static function kurikulumUntukCard(Mk $mk): ?Kurikulum
    {
        $kurikulum = static::penawaranUntukCard($mk)?->kurikulum;

        if ($kurikulum instanceof Kurikulum) {
            return $kurikulum;
        }

        $mk->loadMissing('kurikulum');

        return $mk->kurikulum;
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
