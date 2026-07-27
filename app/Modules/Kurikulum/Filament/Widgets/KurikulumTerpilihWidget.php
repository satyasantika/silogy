<?php

namespace App\Modules\Kurikulum\Filament\Widgets;

use App\Models\User;
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

        // Ikuti role aktif switcher — jangan tampil hanya karena
        // kepemilikan Tim Kurikulum / Admin atau status_tim_kurikulum.
        if (! $user->hasAnyRole(['Tim Kurikulum', 'Admin', 'Admin Program Studi'])) {
            return false;
        }

        return AcademicUnitScope::scopedTimKurikulumUnitIdsFor($user)->isNotEmpty();
    }

    public function mount(): void
    {
        $user = auth()->user();

        // Mode admin prodi: tampilkan kurikulum unitnya apa adanya (tanpa
        // memaksa filter is_active seperti dropdown Tim Kurikulum).
        if ($user instanceof User && $this->isAdminProdiTanpaTimKurikulum($user)) {
            $this->kurikulumId = KurikulumTerpilih::currentId();

            return;
        }

        $this->sinkronkanKurikulumAktif();
    }

    public function updatedKurikulumId(?string $value): void
    {
        $options = KurikulumTerpilih::options(hanyaAktif: true);

        if (filled($value) && array_key_exists($value, $options)) {
            KurikulumTerpilih::set($value);
        } else {
            KurikulumTerpilih::set(array_key_first($options));
        }

        $this->kurikulumId = KurikulumTerpilih::currentId();
    }

    /**
     * Dropdown dashboard Tim Kurikulum hanya menampilkan kurikulum aktif;
     * sesuaikan session bila yang tersimpan non-aktif / di luar opsi.
     */
    protected function sinkronkanKurikulumAktif(): void
    {
        $options = KurikulumTerpilih::options(hanyaAktif: true);
        $currentId = KurikulumTerpilih::currentId();

        if ($currentId !== null && array_key_exists($currentId, $options)) {
            $this->kurikulumId = $currentId;

            return;
        }

        $defaultId = array_key_first($options);
        KurikulumTerpilih::set($defaultId);
        $this->kurikulumId = $defaultId;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();
        $adminProdiMode = $user instanceof User && $this->isAdminProdiTanpaTimKurikulum($user);
        $kurikulum = KurikulumTerpilih::current();
        $options = KurikulumTerpilih::options(hanyaAktif: true);

        // Tim Kurikulum: jangan tampilkan kurikulum non-aktif di kartu.
        if (! $adminProdiMode && $kurikulum !== null && ! array_key_exists($kurikulum->id, $options)) {
            $kurikulum = null;
        }

        return [
            'adminProdiMode' => $adminProdiMode,
            'options' => $options,
            'kurikulum' => $kurikulum,
            'kelolaUrl' => KurikulumResource::getUrl('index'),
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

        // Mode admin prodi mengikuti role aktif, bukan kepemilikan.
        return $user->hasRole('Admin')
            || $user->hasRole('Admin Program Studi');
    }
}
