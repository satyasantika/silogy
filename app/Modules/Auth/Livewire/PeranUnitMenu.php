<?php

namespace App\Modules\Auth\Livewire;

use App\Models\User;
use App\Modules\Auth\Filament\Concerns\HasGantiPeranUnitAction;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Auth\Support\PeranUnitFormFields;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Lab404\Impersonate\Services\ImpersonateManager;
use Livewire\Component;

/**
 * Menu pengguna baru (grid avatar/Nama/Peran/Unit + tombol Keluar,
 * menggantikan dropdown Profile+Logout bawaan Filament sepenuhnya —
 * lihat override resources/views/vendor/filament-panels/components/user-menu.blade.php).
 *
 * Komponen TERPISAH dari RoleSwitcher — tidak menyentuh/menggantikan
 * RoleSwitcher sama sekali, keduanya tampil berdampingan.
 */
class PeranUnitMenu extends Component implements HasActions, HasSchemas
{
    use HasGantiPeranUnitAction;
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function leaveImpersonate(): void
    {
        $manager = app(ImpersonateManager::class);

        if (! $manager->isImpersonating()) {
            return;
        }

        $manager->leave();

        session()->forget(ActiveRole::SESSION_KEY);
        session()->forget(AcademicUnitTerpilih::SESSION_KEY);

        $this->redirect(url('/dashboard'));
    }

    public function render(): View
    {
        $user = auth()->user();
        $roles = $user instanceof User ? ActiveRole::ownedRoleNames($user) : [];
        $peranAktif = $user instanceof User ? PeranUnitFormFields::defaultRole($user) : null;

        return view('filament.auth.peran-unit-menu', [
            'user' => $user,
            'roles' => $roles,
            'peranAktif' => $peranAktif,
            'unitAktifId' => $user instanceof User ? PeranUnitFormFields::defaultUnitId($user) : null,
            'bisaGanti' => $user instanceof User && (
                count($roles) > 1 || PeranUnitFormFields::unitCountForRole($user, $peranAktif) > 1
            ),
            'isImpersonating' => app(ImpersonateManager::class)->isImpersonating(),
        ]);
    }
}
