<?php

namespace App\Modules\Auth\Livewire;

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Tombol di navigasi (antara pencarian dan menu pengguna) untuk user
 * multi-role: memilih role aktif sehingga menu dan hak akses mengikuti
 * role tersebut. Tidak ada opsi "semua peran" — user multi-role selalu
 * punya satu role aktif yang jelas, default ke role pertama bila belum
 * pernah memilih.
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
            // role pertama, karena switcher tidak lagi menawarkan opsi
            // "semua peran" untuk dipilih ulang.
            if (count($roles) > 1) {
                $this->activeRole = $roles[0];
                ActiveRole::set($this->activeRole);
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
