<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Support\MasukDenganGoogle;
use App\Modules\Auth\Support\MasukGoogleDitolak;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;

/**
 * "Masuk dengan Google". Aktif hanya bila `services.google.aktif` menyala dan
 * kredensial klien terisi; selain itu rute memberi 404 seolah tidak ada.
 */
class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        abort_unless(self::aktif(), 404);

        /** @var GoogleProvider $penyedia */
        $penyedia = Socialite::driver('google');

        return $penyedia
            ->scopes(['openid', 'profile', 'email'])
            ->with(array_filter([
                'hd' => config('services.google.domain') ?: null,
                'prompt' => 'select_account',
            ]))
            ->redirect();
    }

    public function callback(Request $request, MasukDenganGoogle $masuk): RedirectResponse
    {
        abort_unless(self::aktif(), 404);

        if ($request->filled('error')) {
            return $this->kembaliKeLogin('Masuk dengan Google dibatalkan.');
        }

        try {
            $user = $masuk->jalankan(Socialite::driver('google')->user());
        } catch (MasukGoogleDitolak $e) {
            return $this->kembaliKeLogin($e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $this->kembaliKeLogin('Masuk dengan Google gagal. Silakan coba lagi.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(Filament::getPanel('admin')->getUrl());
    }

    public static function aktif(): bool
    {
        return (bool) config('services.google.aktif')
            && filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'));
    }

    private function kembaliKeLogin(string $pesan): RedirectResponse
    {
        return redirect(Filament::getPanel('admin')->getLoginUrl())
            ->with('google_error', $pesan);
    }
}
