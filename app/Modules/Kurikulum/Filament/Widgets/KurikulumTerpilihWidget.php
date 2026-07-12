<?php

namespace App\Modules\Kurikulum\Filament\Widgets;

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kelas\Filament\Resources\KelasMkResource;
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

        if (! $user instanceof User || $user->hasRole('Super Admin')) {
            // Pengelolaan kurikulum didelegasikan — superadmin tidak
            // membutuhkan jalan pintas ini.
            return false;
        }

        return AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user)->isNotEmpty();
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
        $user = auth()->user();
        $kurikulum = KurikulumTerpilih::current();

        return [
            'adminProdiMode' => $user instanceof User && $this->isAdminProdiTanpaTimKurikulum($user),
            'options' => KurikulumTerpilih::options(),
            'kurikulum' => $kurikulum,
            'kelolaUrl' => $kurikulum
                ? KurikulumResource::getUrl('edit', ['record' => $kurikulum])
                : KurikulumResource::getUrl('index'),
            'kelolaKelasUrl' => KelasMkResource::getUrl('index'),
        ];
    }

    /**
     * Admin prodi murni (role Admin/Admin Program Studi tanpa penugasan
     * Tim Kurikulum sungguhan) hanya perlu melihat satu kurikulum unitnya
     * dan jalan pintas ke Kelas MK — bukan dropdown pilih-kurikulum penuh
     * yang ditujukan untuk Tim Kurikulum yang bisa menangani banyak unit.
     */
    protected function isAdminProdiTanpaTimKurikulum(User $user): bool
    {
        if ($user->hasRole('Super Admin')) {
            return false;
        }

        $timKurikulumSungguhan = AcademicUnitUser::query()
            ->where('user_id', $user->id)
            ->where('status_tim_kurikulum', true)
            ->exists();

        if ($timKurikulumSungguhan) {
            return false;
        }

        if (! AcademicUnitScope::userHasPivotOnUnitType($user, 'study_program')) {
            return false;
        }

        return ActiveRole::userOwnsRoleName($user, 'Admin')
            || ActiveRole::userOwnsRoleName($user, 'Admin Program Studi');
    }
}
