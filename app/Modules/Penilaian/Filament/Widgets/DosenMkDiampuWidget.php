<?php

namespace App\Modules\Penilaian\Filament\Widgets;

use App\Models\User;
use App\Modules\Kalkulasi\Filament\Support\Concerns\CanAccessDashboardWidgets;
use App\Modules\MK\Filament\Support\Concerns\HasDosenPengampuMkScope;
use App\Modules\MK\Models\Mk;
use App\Modules\Penilaian\Support\MkDiampuTerpilih;
use Filament\Widgets\Widget;

/**
 * Pemilih "mata kuliah sedang dikerjakan" untuk Dosen Pengampu. Terpisah
 * dari mk-terpilih-banner (koordinator) karena Dosen Pengampu murni
 * (tanpa jadi koordinator) tidak tercakup scope MkTerpilih.
 */
class DosenMkDiampuWidget extends Widget
{
    use CanAccessDashboardWidgets;
    use HasDosenPengampuMkScope;

    protected string $view = 'filament.modules.penilaian.widgets.dosen-mk-diampu';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public ?string $mkId = null;

    public static function canView(): bool
    {
        return static::canViewDashboardWidgets()
            && static::isDashboardDosenPengampu();
    }

    public function mount(): void
    {
        $this->mkId = MkDiampuTerpilih::currentId();
    }

    public function updatedMkId(): void
    {
        MkDiampuTerpilih::set($this->mkId);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        $options = $user instanceof User
            ? Mk::query()
                ->whereIn('id', static::scopedDiampuMkIds($user))
                ->orderBy('nama')
                ->pluck('nama', 'id')
                ->all()
            : [];

        return ['options' => $options];
    }
}
