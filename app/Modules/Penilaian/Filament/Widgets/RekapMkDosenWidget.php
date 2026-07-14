<?php

namespace App\Modules\Penilaian\Filament\Widgets;

use App\Models\User;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use App\Modules\Penilaian\Services\PenilaianDosenService;
use Filament\Widgets\Widget;

/**
 * Jalan pintas dashboard untuk Dosen Pengampu: rekap jumlah mata kuliah yang
 * diampu per semester, masing-masing bertaut ke /penilaian dengan semester
 * itu langsung terfilter.
 */
class RekapMkDosenWidget extends Widget
{
    protected string $view = 'filament.modules.penilaian.widgets.rekap-mk-dosen';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        // Ikuti role aktif switcher (hasRole), bukan kepemilikan role.
        return $user instanceof User && $user->hasRole('Dosen Pengampu');
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        $rekap = $user instanceof User
            ? PenilaianDosenService::rekapMkPerSemester($user)
            : collect();

        $rows = $rekap->map(fn (array $row): array => [
            'semester' => $row['semester'],
            'jumlah_mk' => $row['jumlah_mk'],
            'url' => PenilaianDosenResource::getUrl('index', ['semester_id' => $row['semester']->id]),
        ]);

        return [
            'rows' => $rows,
            'penilaianUrl' => PenilaianDosenResource::getUrl('index'),
        ];
    }
}
