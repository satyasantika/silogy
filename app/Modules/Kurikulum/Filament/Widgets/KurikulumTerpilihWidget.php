<?php

namespace App\Modules\Kurikulum\Filament\Widgets;

use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Filament\Widgets\Widget;

/**
 * Jalan pintas tim kurikulum di dashboard: pilih kurikulum yang sedang
 * dikerjakan (tersimpan di session, diikuti seluruh halaman kurikulum)
 * dan tautan langsung ke pengelolaannya.
 */
class KurikulumTerpilihWidget extends Widget
{
    protected string $view = 'filament.modules.kurikulum.widgets.kurikulum-terpilih';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public ?string $kurikulumId = null;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->hasRole('Super Admin')
                || AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user)->isNotEmpty());
    }

    public function mount(): void
    {
        $this->kurikulumId = KurikulumTerpilih::currentId();
    }

    public function updatedKurikulumId(?string $value): void
    {
        KurikulumTerpilih::set(blank($value) ? null : $value);
        $this->kurikulumId = KurikulumTerpilih::currentId();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $kurikulum = KurikulumTerpilih::current();

        return [
            'options' => KurikulumTerpilih::options(),
            'kurikulum' => $kurikulum,
            'kelolaUrl' => $kurikulum
                ? KurikulumResource::getUrl('edit', ['record' => $kurikulum])
                : KurikulumResource::getUrl('index'),
        ];
    }
}
