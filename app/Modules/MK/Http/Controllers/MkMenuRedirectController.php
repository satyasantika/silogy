<?php

namespace App\Modules\MK\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Pages\CplCpmkMatrix;
use App\Modules\MK\Filament\Pages\SubcpmkAsesmenMatrix;
use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Filament\Resources\SubcpmkResource;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource;
use App\Modules\Penilaian\Filament\Resources\PesertaKelasResource;
use App\Modules\MK\Policies\MataKuliahKoordinatorPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class MkMenuRedirectController extends Controller
{
    /**
     * @var array<string, class-string>
     */
    protected const MENU_RESOURCES = [
        'cpmk' => CpmkResource::class,
        'subcpmk' => SubcpmkResource::class,
        'asesmen' => KomponenPenilaianResource::class,
        'mahasiswa' => PesertaKelasResource::class,
    ];

    /**
     * @var array<string, class-string>
     */
    protected const MENU_PAGES = [
        'cpl-cpmk' => CplCpmkMatrix::class,
        'subcpmk-asesmen' => SubcpmkAsesmenMatrix::class,
    ];

    public function __invoke(Request $request, Mk $mk, string $menu): RedirectResponse
    {
        abort_unless(
            app(MataKuliahKoordinatorPolicy::class)->view($request->user(), $mk),
            403,
        );

        $menu = strtolower($menu);

        abort_unless(
            Arr::has(self::MENU_RESOURCES, $menu) || Arr::has(self::MENU_PAGES, $menu),
            404,
        );

        $kurikulum = KurikulumTerpilih::current();

        abort_unless(
            $kurikulum !== null && MkTerpilih::mkDitawarkanPadaKurikulum($mk, $kurikulum),
            404,
        );

        MkTerpilih::set($mk->id);

        if (Arr::has(self::MENU_RESOURCES, $menu)) {
            $resourceClass = self::MENU_RESOURCES[$menu];

            return redirect()->to($resourceClass::getUrl('index'));
        }

        $pageClass = self::MENU_PAGES[$menu];

        return redirect()->to($pageClass::getUrl());
    }
}
