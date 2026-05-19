<?php

declare(strict_types=1);

namespace App\Auth\Http\Responses;

use App\Filament\Pages\Dashboard;
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
        $panel = Filament::getCurrentOrDefaultPanel();

        $fallback = $panel->getHomeUrl() ?? Dashboard::getUrl(panel: $panel->getId());

        return redirect()->intended($fallback);
    }
}
