<?php

namespace App\Modules\Auth\Livewire;

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Select di sidebar untuk user multi-role: memilih role aktif
 * sehingga menu dan hak akses mengikuti role tersebut.
 */
class RoleSwitcher extends Component
{
    public ?string $activeRole = null;

    public function mount(): void
    {
        $user = auth()->user();

        $this->activeRole = $user instanceof User
            ? ActiveRole::currentFor($user)
            : null;
    }

    public function updatedActiveRole(?string $value): void
    {
        ActiveRole::set(blank($value) ? null : $value);

        // Muat ulang halaman agar navigasi dan seluruh policy dibangun
        // ulang dengan role aktif yang baru.
        $this->redirect(url()->previous() ?: url('/dashboard'));
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
