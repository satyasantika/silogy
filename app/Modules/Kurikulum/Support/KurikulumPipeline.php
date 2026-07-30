<?php

namespace App\Modules\Kurikulum\Support;

use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Filament\Resources\CplResource;
use App\Modules\CPL\Models\Cpl;
use App\Modules\Kurikulum\Filament\Pages\AnalisisMkFakultas;
use App\Modules\Kurikulum\Filament\Pages\AnalisisMkProdi;
use App\Modules\Kurikulum\Filament\Pages\AnalisisMkUniversitas;
use App\Modules\Kurikulum\Filament\Pages\CplBokMatrix;
use App\Modules\Kurikulum\Filament\Pages\CplMkMatrix;
use App\Modules\Kurikulum\Filament\Pages\ProfilCplMatrix;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\MK\Filament\Resources\MkResource;
use App\Modules\MK\Filament\Resources\MkUnitResource;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;

final class KurikulumPipeline
{
    /**
     * @return list<array{key: string, label: string, resource: class-string}>
     */
    public static function steps(bool $isProdi): array
    {
        return [
            ...($isProdi ? [['key' => 'profil_lulusan', 'label' => 'Profil Lulusan', 'resource' => ProfilLulusanResource::class]] : []),
            ['key' => 'cpl', 'label' => 'CPL', 'resource' => CplResource::class],
            ['key' => 'bok', 'label' => 'BoK', 'resource' => BokResource::class],
            ['key' => 'mk', 'label' => 'MK', 'resource' => MkResource::class],
            ...($isProdi ? [['key' => 'mk_unit', 'label' => 'Penawaran MK', 'resource' => MkUnitResource::class]] : []),
        ];
    }

    public static function hasData(string $key, Kurikulum $kurikulum): bool
    {
        return match ($key) {
            'profil_lulusan' => ProfilLulusan::query()->where('kurikulum_id', $kurikulum->id)->exists(),
            'cpl' => CplBokAdaptasiScope::scopeVisibleCpl(Cpl::query(), $kurikulum->id)->exists(),
            'bok' => CplBokAdaptasiScope::scopeVisibleBok(Bok::query(), $kurikulum->id)->exists(),
            'mk' => Mk::query()->where('kurikulum_id', $kurikulum->id)->exists(),
            'mk_unit' => MkUnit::query()->where('kurikulum_id', $kurikulum->id)->exists(),
            default => false,
        };
    }

    /**
     * @return array{prev: ?array{label: string, url: string}, next: ?array{label: string, url: string}}
     */
    public static function navFor(string $currentKey): array
    {
        $kurikulum = KurikulumTerpilih::current();

        if (! $kurikulum instanceof Kurikulum) {
            return ['prev' => null, 'next' => null];
        }

        $isProdi = $kurikulum->academicUnit?->isProdi() ?? false;
        $steps = self::steps($isProdi);
        $index = collect($steps)->search(fn (array $step): bool => $step['key'] === $currentKey);

        if ($index === false) {
            return ['prev' => null, 'next' => null];
        }

        $prevStep = $steps[$index - 1] ?? null;
        $nextStep = $steps[$index + 1] ?? null;

        $prev = $prevStep !== null
            ? ['label' => $prevStep['label'], 'url' => $prevStep['resource']::getUrl('index')]
            : ['label' => 'Daftar Kurikulum', 'url' => KurikulumResource::getUrl('index')];

        $next = null;

        if ($nextStep !== null
            && self::hasData($currentKey, $kurikulum)
            && ($nextStep['key'] !== 'mk_unit' || MkUnitResource::canAccess())
        ) {
            $next = ['label' => $nextStep['label'], 'url' => $nextStep['resource']::getUrl('index')];
        }

        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * Daftar link Interaksi & Pelaporan untuk langkah terakhir pipeline.
     * Hanya bergantung pada tipe unit kurikulum & role user (stabil dalam
     * satu request) — TIDAK bergantung pada hasData(), supaya aman dipakai
     * langsung sebagai array actions eager (ActionGroup::make() Filament
     * hanya menerima array, bukan closure). Gating "tampil atau tidak"
     * (yang memang perlu reaktif terhadap data, mis. setelah import massal)
     * ada di isFinishStepReady(), dipanggil lewat closure ->visible().
     *
     * @return list<array{label: string, url: string}>
     */
    public static function finishLinksFor(string $currentKey): array
    {
        $kurikulum = KurikulumTerpilih::current();

        if (! $kurikulum instanceof Kurikulum) {
            return [];
        }

        $isProdi = $kurikulum->academicUnit?->isProdi() ?? false;
        $steps = self::steps($isProdi);
        $lastStep = end($steps);

        if ($lastStep === false || $lastStep['key'] !== $currentKey) {
            return [];
        }

        $destinations = [];

        if ($isProdi && ProfilCplMatrix::canAccess()) {
            $destinations[] = ['label' => 'Interaksi Profil × CPL', 'url' => ProfilCplMatrix::getUrl()];
        }

        if (CplBokMatrix::canAccess()) {
            $destinations[] = ['label' => 'Interaksi CPL × BoK', 'url' => CplBokMatrix::getUrl()];
        }

        if (CplMkMatrix::canAccess()) {
            $destinations[] = ['label' => 'Interaksi CPL × MK', 'url' => CplMkMatrix::getUrl()];
        }

        $laporanResource = match (true) {
            $isProdi => AnalisisMkProdi::class,
            $kurikulum->academicUnit?->isFaculty() => AnalisisMkFakultas::class,
            default => AnalisisMkUniversitas::class,
        };

        if ($laporanResource::canAccess()) {
            $destinations[] = ['label' => 'Pelaporan Analisis MK', 'url' => $laporanResource::getUrl()];
        }

        return $destinations;
    }

    /**
     * Apakah langkah terakhir pipeline sudah terisi datanya — dipanggil
     * lewat closure ->visible() supaya reaktif walau skema halaman sudah
     * ter-cache lebih awal dalam request yang sama (mis. sebelum import
     * massal commit).
     */
    public static function isFinishStepReady(string $currentKey): bool
    {
        $kurikulum = KurikulumTerpilih::current();

        if (! $kurikulum instanceof Kurikulum) {
            return false;
        }

        $isProdi = $kurikulum->academicUnit?->isProdi() ?? false;
        $steps = self::steps($isProdi);
        $lastStep = end($steps);

        return $lastStep !== false
            && $lastStep['key'] === $currentKey
            && self::hasData($currentKey, $kurikulum);
    }
}
