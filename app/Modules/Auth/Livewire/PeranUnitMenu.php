<?php

namespace App\Modules\Auth\Livewire;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use App\Modules\Auth\Filament\Actions\GantiPasswordAction;
use App\Modules\Auth\Filament\Actions\KeluarAction;
use App\Modules\Auth\Filament\Pages\PilihPeranUnit;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Auth\Support\PeranUnitFormFields;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Identitas pengguna di footer sidebar: menu (tema, profil, kata sandi,
 * ganti peran) + tombol Keluar berkonfirmasi. Saat sidebar diminimize
 * hanya Keluar yang tampil; identitas topbar bawaan Filament kembali
 * muncul (lihat AdminPanelProvider STYLES_AFTER).
 */
class PeranUnitMenu extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function gantiPasswordAction(): Action
    {
        return GantiPasswordAction::make();
    }

    public function keluarAction(): Action
    {
        return KeluarAction::make()
            ->iconButton()
            ->tooltip('Keluar');
    }

    public function render(): View
    {
        $user = auth()->user();
        $roles = $user instanceof User ? ActiveRole::ownedRoleNames($user) : [];
        $peranAktif = $user instanceof User ? PeranUnitFormFields::defaultRole($user) : null;
        $bisaGanti = $user instanceof User && (
            count($roles) > 1 || PeranUnitFormFields::roleMembutuhkanPilihanUnit($user, $peranAktif)
        );

        return view('filament.auth.peran-unit-menu', [
            'user' => $user,
            'roles' => $roles,
            'peranAktif' => $peranAktif,
            'unitAktifId' => $user instanceof User ? PeranUnitFormFields::defaultUnitId($user) : null,
            'bisaGanti' => $bisaGanti,
            'profileUrl' => filament()->getProfileUrl() ?? EditProfile::getUrl(),
            'pilihPeranUnitUrl' => PilihPeranUnit::getUrl(),
        ]);
    }
}
