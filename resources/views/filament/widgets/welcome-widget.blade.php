@php
    use App\Models\User;
    use App\Modules\Auth\Support\ActiveRole;
    use App\Modules\Auth\Support\PeranUnitFormFields;

    $user = filament()->auth()->user();
    $roles = $user instanceof User ? ActiveRole::ownedRoleNames($user) : [];
    $peranAktif = $user instanceof User ? PeranUnitFormFields::defaultRole($user) : null;
    $bisaGanti = $user instanceof User
        && (count($roles) > 1 || PeranUnitFormFields::roleMembutuhkanPilihanUnit($user, $peranAktif));
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
                    @if ($bisaGanti)
                        {{ $this->gantiPeranUnitAction() }}
                    @endif
                </p>
            @endif
        </div>

        <div class="fi-account-widget-logout-form">
            {{ $this->keluarAction() }}
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
