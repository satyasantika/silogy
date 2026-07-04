<?php

namespace App\Modules\Kurikulum\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BoK\Filament\Resources\BokResource;
use App\Modules\CPL\Filament\Resources\CplResource;
use App\Modules\Kurikulum\Filament\Resources\ProfilLulusanResource;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkResource;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class KurikulumMenuRedirectController extends Controller
{
    use AuthorizesRequests;

    /**
     * @var array<string, class-string>
     */
    protected const MENU_RESOURCES = [
        'profil' => ProfilLulusanResource::class,
        'cpl' => CplResource::class,
        'bok' => BokResource::class,
        'mk' => MkResource::class,
    ];

    public function __invoke(Request $request, Kurikulum $kurikulum, string $menu): RedirectResponse
    {
        $this->authorize('view', $kurikulum);

        $menu = strtolower($menu);

        abort_unless(Arr::has(self::MENU_RESOURCES, $menu), 404);

        $kurikulum->loadMissing('academicUnit');

        if ($menu === 'profil' && ! $kurikulum->academicUnit?->isProdi()) {
            abort(404);
        }

        KurikulumTerpilih::set($kurikulum->id);

        $resourceClass = self::MENU_RESOURCES[$menu];

        return redirect()->to($resourceClass::getUrl('index'));
    }
}
