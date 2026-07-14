<?php

namespace App\Modules\MK\Filament\Widgets;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kelas\Filament\Resources\KelasMkResource;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\MK\Filament\Resources\MataKuliahKoordinatorResource;
use App\Modules\MK\Filament\Support\Concerns\HasKoordinatorMkScope;
use App\Modules\MK\Models\Mk;
use Filament\Widgets\Widget;

/**
 * Jalan pintas dashboard untuk Koordinator Mata Kuliah: tautan ke Kelas MK
 * dan Mata Kuliah Koordinator, beserta rekap berapa MK yang dikoordinasikan,
 * di berapa kurikulum, dan di unit mana saja.
 */
class KoordinatorMkAksesWidget extends Widget
{
    use HasKoordinatorMkScope;

    protected string $view = 'filament.modules.mk.widgets.koordinator-mk-akses';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        // Ikuti role aktif switcher (hasRole), bukan kepemilikan role.
        return $user instanceof User && $user->hasRole('Koordinator Mata Kuliah');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        $mkIds = $user instanceof User ? static::scopedKoordinatorMkIds($user) : collect();

        $unitIds = Mk::query()
            ->whereIn('id', $mkIds)
            ->pluck('academic_unit_id')
            ->unique()
            ->values();

        $unitNama = AcademicUnit::query()
            ->whereIn('id', $unitIds)
            ->orderBy('nama')
            ->pluck('nama');

        $jumlahKurikulum = $unitIds->isEmpty()
            ? 0
            : Kurikulum::query()->whereIn('academic_unit_id', $unitIds)->count();

        return [
            'jumlahMk' => $mkIds->count(),
            'jumlahKurikulum' => $jumlahKurikulum,
            'unitNama' => $unitNama,
            'kelasMkUrl' => KelasMkResource::getUrl('index'),
            'mataKuliahKoordinatorUrl' => MataKuliahKoordinatorResource::getUrl('index'),
        ];
    }
}
