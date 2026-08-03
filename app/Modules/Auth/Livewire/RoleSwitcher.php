<?php

namespace App\Modules\Auth\Livewire;

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Headless switcher peran aktif: auto-set role pertama bila user
 * multi-role belum memilih. UI ganti peran ada di menu identitas
 * pengguna (footer sidebar / user-menu topbar), bukan ikon nav terpisah.
 */
class RoleSwitcher extends Component
{
    public ?string $activeRole = null;

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $this->activeRole = ActiveRole::currentFor($user);

        if ($this->activeRole === null) {
            $roles = ActiveRole::ownedRoleNames($user);

            // User multi-role belum pernah memilih role aktif — default ke
            // role pertama untuk tampilan komponen ini SAJA, TANPA dipersist
            // ke sesi lewat ActiveRole::set().
            //
            // Dulu dipersist di sini: begitu user mendapat role baru
            // pertengahan sesi (mis. Dosen Pengampu diangkat jadi
            // Koordinator Mata Kuliah oleh Tim Kurikulum), page load
            // berikutnya langsung mengunci role aktifnya ke role lama
            // (alfabetis pertama, "Dosen Pengampu") secara permanen — role
            // baru jadi tidak pernah terjangkau tanpa admin turun tangan
            // reset sesi. Nilai default ini tidak perlu dipersist:
            // PeranUnitFormFields::defaultRole() (dipakai
            // NavigationGroupPeran/NavigationSortPeran/dsb.) sudah
            // menghitung fallback yang sama persis di setiap pemanggilan.
            if (count($roles) > 1) {
                $this->activeRole = $roles[0];
            }
        }
    }

    public function updatedActiveRole(?string $value): void
    {
        ActiveRole::set(blank($value) ? null : $value);

        // Selalu kembali ke dashboard agar widget/menu role baru langsung
        // terlihat, tanpa tetap di halaman yang mungkin tidak relevan.
        $this->redirect(url('/dashboard'));
    }

    /**
     * @return list<string>
     */
    public function getOwnedRolesProperty(): array
    {
        $user = auth()->user();

        return $user instanceof User
            ? ActiveRole::ownedRoleNames($user)
            : [];
    }

    public function render(): View
    {
        return view('filament.auth.role-switcher', [
            'roles' => $this->getOwnedRolesProperty(),
        ]);
    }
}
