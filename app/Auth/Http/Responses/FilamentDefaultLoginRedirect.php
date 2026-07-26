<?php

declare(strict_types=1);

namespace App\Auth\Http\Responses;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Modules\Auth\Filament\Pages\PilihPeranUnit;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Panel ber-path root tanpa route filament.home (karena `/` adalah landing Laravel);
 * Respons bawaan memanggil Filament::getUrl() yang kemudian jatuh ke URL aplikasi kosong — bukan /dashboard.
 */
final class FilamentDefaultLoginRedirect implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        // Reset peran aktif switcher agar hak Admin prodi (mis. Kelas MK)
        // tidak tertahan oleh pilihan role sebelumnya di sesi lama.
        session()->forget(ActiveRole::SESSION_KEY);

        // Sama alasannya: unit yang dipilih di sesi login sebelumnya tidak
        // boleh terbawa ke sesi baru.
        session()->forget(AcademicUnitTerpilih::SESSION_KEY);

        $panel = Filament::getCurrentOrDefaultPanel();

        $fallback = $panel->getHomeUrl() ?? Dashboard::getUrl(panel: $panel->getId());

        $user = auth()->user();

        // User multi-role belum pernah menegaskan peran aktifnya lewat
        // gerbang ini — arahkan ke sana dulu (bukan langsung ke dashboard)
        // supaya role baru yang diberikan tidak tersembunyi di belakang
        // pilihan alfabetis default RoleSwitcher. Dimensi unit sengaja
        // TIDAK dicek di sini — PilihPeranUnit menampilkan/menyembunyikan
        // picker unit sendiri berdasarkan role yang terpilih.
        if ($user instanceof User && count(ActiveRole::ownedRoleNames($user)) > 1) {
            $fallback = PilihPeranUnit::getUrl(panel: $panel->getId());
        }

        return redirect()->intended($fallback);
    }
}
