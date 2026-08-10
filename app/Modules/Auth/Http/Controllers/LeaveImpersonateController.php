<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use Illuminate\Http\RedirectResponse;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * Plain (non-Livewire) route untuk keluar dari impersonate.
 *
 * ImpersonateManager::leave() meregenerasi session ID dan CSRF token
 * (lewat quietLogin -> Session::regenerate(true)). Memicu ini dari dalam
 * aksi Livewire (POST /livewire/update) merotasi token itu di tengah
 * siklus request AJAX, sementara meta tag CSRF di halaman yang sedang
 * dibuka masih membawa token lama — commit Livewire berikutnya yang
 * memakai token lama itu ditolak (419) sebelum redirect sempat me-reload
 * halaman. Navigasi browser biasa (GET, bukan fetch()) tidak kena masalah
 * ini karena tidak ada token lama yang "nyangkut" di client.
 */
class LeaveImpersonateController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $manager = app(ImpersonateManager::class);

        if (! $manager->isImpersonating()) {
            return redirect()->to(url('/dashboard'));
        }

        $manager->leave();

        session()->forget(ActiveRole::SESSION_KEY);
        session()->forget(AcademicUnitTerpilih::SESSION_KEY);

        return redirect()->to(session()->pull('impersonate.back_to') ?? url('/dashboard'));
    }
}
