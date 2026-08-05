<?php

namespace App\Modules\Kurikulum\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Institusi\Support\AcademicUnitScope;
use App\Modules\Kurikulum\Filament\Pages\AnalisisCplMahasiswa;
use App\Modules\Kurikulum\Filament\Pages\GrafikCpl;
use App\Modules\Kurikulum\Filament\Pages\HasilAnalisisCpl;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Support\Filament\DelegasiMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

/**
 * Badge card kurikulum Pimpinan → set kurikulum terpilih lalu buka
 * salah satu menu laporan (hasil / grafik / per mahasiswa).
 */
class KurikulumLaporanPimpinanRedirectController extends Controller
{
    /**
     * @var array<string, class-string>
     */
    protected const MENU_PAGES = [
        'hasil' => HasilAnalisisCpl::class,
        'grafik' => GrafikCpl::class,
        'mahasiswa' => AnalisisCplMahasiswa::class,
    ];

    public function __invoke(Request $request, Kurikulum $kurikulum, string $menu): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User && DelegasiMenu::peranAktifPimpinan($user), 403);
        abort_unless(
            AcademicUnitScope::scopedPimpinanUnitIdsWithDescendantsFor($user)
                ->contains($kurikulum->academic_unit_id),
            403,
        );

        $menu = strtolower($menu);

        abort_unless(Arr::has(self::MENU_PAGES, $menu), 404);

        KurikulumTerpilih::set($kurikulum->id);

        $pageClass = self::MENU_PAGES[$menu];

        return redirect()->to($pageClass::getUrl());
    }
}
