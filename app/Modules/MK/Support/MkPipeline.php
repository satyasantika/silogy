<?php

namespace App\Modules\MK\Support;

use App\Models\User;
use App\Modules\Kalender\Support\SemesterTerpilih;
use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource;
use App\Modules\MK\Filament\Resources\SubcpmkResource;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Services\MataKuliahKoordinatorService;
use App\Modules\Penilaian\Filament\Pages\LaporanKoordinator;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Filament\Resources\PesertaKelasResource;
use App\Modules\Penilaian\Models\KomponenPenilaian;

/**
 * Alur back/next koordinator MK — pola sama dengan KurikulumPipeline
 * untuk Tim Kurikulum: CPMK → Sub-CPMK → Asesmen → Laporan → Mahasiswa.
 */
final class MkPipeline
{
    /**
     * @return list<array{key: string, label: string, url: callable(): string, canAccess: callable(): bool}>
     */
    public static function steps(): array
    {
        return [
            [
                'key' => 'cpmk',
                'label' => 'CPMK',
                'url' => fn (): string => CpmkResource::getUrl('index'),
                'canAccess' => fn (): bool => CpmkResource::canAccess(),
            ],
            [
                'key' => 'subcpmk',
                'label' => 'Sub-CPMK',
                'url' => fn (): string => SubcpmkResource::getUrl('index'),
                'canAccess' => fn (): bool => SubcpmkResource::canAccess(),
            ],
            [
                'key' => 'asesmen',
                'label' => 'Asesmen',
                'url' => fn (): string => KomponenPenilaianResource::getUrl('index'),
                'canAccess' => fn (): bool => KomponenPenilaianResource::canAccess(),
            ],
            [
                'key' => 'laporan',
                'label' => 'Laporan',
                'url' => fn (): string => LaporanKoordinator::getUrl(),
                'canAccess' => fn (): bool => LaporanKoordinator::canAccess(),
            ],
            [
                'key' => 'mahasiswa',
                'label' => 'Mahasiswa',
                'url' => fn (): string => PesertaKelasResource::getUrl('index'),
                'canAccess' => fn (): bool => PesertaKelasResource::canAccess(),
                // Mahasiswa & Laporan saling bertaut, bukan mengikuti urutan
                // linear array: back dari Mahasiswa kembali ke Asesmen
                // (bukan ke Laporan), next dari Mahasiswa ke Laporan.
                'prevKey' => 'asesmen',
                'nextKey' => 'laporan',
            ],
        ];
    }

    public static function hasData(string $key, Mk $mk, User $user): bool
    {
        $menu = MataKuliahKoordinatorService::ketersediaanPenilaian($mk, $user);

        return match ($key) {
            'cpmk' => $menu['cpmk'],
            'subcpmk' => $menu['subcpmk'],
            'asesmen' => $menu['asesmen'],
            // Laporan tidak punya "isi data" tersendiri; next ke Mahasiswa
            // dibuka begitu koordinator sudah di langkah laporan.
            'laporan' => true,
            'mahasiswa' => $menu['mahasiswa'],
            default => false,
        };
    }

    /**
     * @return array{prev: ?array{label: string, url: string}, next: ?array{label: string, url: string}}
     */
    public static function navFor(string $currentKey): array
    {
        $user = auth()->user();
        $mk = MkTerpilih::current();

        if (! $user instanceof User || ! $mk instanceof Mk) {
            return ['prev' => null, 'next' => null];
        }

        $steps = self::steps();
        $index = collect($steps)->search(fn (array $step): bool => $step['key'] === $currentKey);

        if ($index === false) {
            return ['prev' => null, 'next' => null];
        }

        $step = $steps[$index];

        $prevStep = isset($step['prevKey'])
            ? collect($steps)->firstWhere('key', $step['prevKey'])
            : ($steps[$index - 1] ?? null);

        $nextStep = isset($step['nextKey'])
            ? collect($steps)->firstWhere('key', $step['nextKey'])
            : ($steps[$index + 1] ?? null);

        $prev = $prevStep !== null
            ? ['label' => $prevStep['label'], 'url' => ($prevStep['url'])()]
            : ['label' => 'Mata Kuliah', 'url' => MataKuliahKoordinatorResource::getUrl('index')];

        $next = null;

        if ($nextStep !== null
            && self::hasData($currentKey, $mk, $user)
            && ($nextStep['canAccess'])()
        ) {
            $next = ['label' => $nextStep['label'], 'url' => ($nextStep['url'])()];
        }

        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * Ketersediaan komponen penilaian pada MK + SEMESTER TERPILIH (beda dari
     * hasData('asesmen', ...) yang MK-wide) — dipakai khusus untuk
     * menampilkan tombol Laporan/Mahasiswa pada langkah Asesmen, supaya
     * keduanya benar-benar mengikuti semester yang sedang dipilih.
     */
    public static function hasAsesmenDataUntukSemesterTerpilih(Mk $mk, User $user): bool
    {
        $semesterId = SemesterTerpilih::berlakuUntukUser($user)
            ? SemesterTerpilih::currentId($mk->id)
            : null;

        return KomponenPenilaian::query()
            ->where('mk_id', $mk->id)
            ->when(filled($semesterId), fn ($query) => $query->where('semester_id', $semesterId))
            ->exists();
    }
}
