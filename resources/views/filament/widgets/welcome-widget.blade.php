@php
    use App\Models\User;
    use App\Modules\Auth\Support\ActiveRole;
    use App\Modules\Auth\Support\PeranUnitFormFields;
    use App\Modules\Institusi\Models\AcademicUnit;

    $user = filament()->auth()->user();
    $roles = $user instanceof User ? ActiveRole::ownedRoleNames($user) : [];
    $peranAktif = $user instanceof User ? PeranUnitFormFields::defaultRole($user) : null;
    $unitAktifId = $user instanceof User ? PeranUnitFormFields::defaultUnitId($user) : null;
    $unitAktifNama = $unitAktifId ? AcademicUnit::find($unitAktifId)?->nama_lengkap : null;
    $bisaGanti = $user instanceof User
        && (count($roles) > 1 || ($peranAktif !== null && PeranUnitFormFields::unitCountForRole($user, $peranAktif) > 1));
    $tanpaPeran = $user instanceof User && count($roles) === 0;
@endphp

<x-filament-widgets::widget class="fi-account-widget">
    <x-filament::section>
        <x-filament-panels::avatar.user size="lg" :user="$user" loading="lazy" />

        <div class="fi-account-widget-main">
            <h2 class="fi-account-widget-heading">
                {{ __('filament-panels::widgets/account-widget.welcome', ['app' => config('app.name')]) }}
            </h2>

            <p class="fi-account-widget-user-name">
                {{ filament()->getUserName($user) }}
            </p>

            @if ($tanpaPeran)
                <p class="fi-color-danger fi-text-color-600" style="font-size:.8125rem;font-weight:600;margin-top:2px;">
                    Anda belum memiliki peran/unit. Hubungi admin.
                </p>
            @else
                <p style="font-size:.8125rem;font-weight:500;opacity:.7;margin-top:2px;">
                    Anda berperan sebagai {{ $peranAktif }}
                    @if ($unitAktifNama)
                        &middot; Unit: {{ $unitAktifNama }}
                    @endif
                    @if ($bisaGanti)
                        {{ $this->gantiPeranUnitAction() }}
                    @endif
                </p>
            @endif
        </div>

        <form action="{{ filament()->getLogoutUrl() }}" method="post" class="fi-account-widget-logout-form">
            @csrf
            <x-filament::button color="gray" :icon="\Filament\Support\Icons\Heroicon::ArrowLeftEndOnRectangle" :icon-alias="\Filament\View\PanelsIconAlias::WIDGETS_ACCOUNT_LOGOUT_BUTTON" labeled-from="sm" tag="button" type="submit">
                {{ __('filament-panels::widgets/account-widget.actions.logout.label') }}
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-widgets::widget>
