<?php

namespace App\Modules\Simulasi\Http\Controllers;

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use App\Modules\Panduan\Support\PeranPanduan;
use App\Modules\Simulasi\Support\Ranah;
use App\Modules\Simulasi\Support\SesiTab;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login di dalam tab sandbox, memakai tiket sekali pakai dari CobaPeranController.
 *
 * Hanya terjangkau lewat /s/<token>/simulasi/masuk: middleware SesiSandboxTab
 * sudah memastikan token sah dan memisahkan sesinya. Tanpa awalan tab rute
 * ini 404, dan tiket yang sudah dipakai tidak bisa dipakai lagi.
 */
class MasukSandboxController
{
    public function __invoke(Request $request): RedirectResponse
    {
        $tab = $request->attributes->get('sandbox_tab');
        abort_if($tab === null, 404);

        $catatan = SesiTab::pakaiTiket($tab['token']);
        abort_if($catatan === null, 404);

        $user = Ranah::sebagai($catatan['jalan'], fn () => User::query()->find($catatan['user']));
        abort_if($user === null, 404);

        Auth::login($user, remember: false);
        $request->session()->regenerate();

        // Peran dan unit dipilihkan di sini supaya pengunjung langsung mendarat
        // di dasbor peran yang ia baca. Keduanya memvalidasi kepemilikan
        // sendiri, jadi pemetaan yang salah gagal tertutup.
        $peranAktif = PeranPanduan::peranUntuk($catatan['peran']);

        ActiveRole::set($peranAktif);
        AcademicUnitTerpilih::set(
            AcademicUnitTerpilih::scopedUnitIdsForRole($user, $peranAktif)->first(),
        );

        $request->session()->put('silogy_sesi_simulasi', $catatan['jalan']);
        $request->session()->put('silogy_sesi_simulasi_peran', $catatan['peran']);

        return redirect()->to('/dashboard');
    }
}
