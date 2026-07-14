@php
    use App\Models\User;
    use App\Modules\Auth\Support\ActiveRole;

    $user = filament()->auth()->user();
    $peranAktif = '';

    if ($user instanceof User) {
        $peranAktif = ActiveRole::currentFor($user);

        if ($peranAktif === null) {
            // Belum pernah memilih role aktif — tampilkan role yang akan
            // dijadikan default oleh switcher (lihat RoleSwitcher::mount()),
            // supaya tidak menampilkan seluruh role sesaat sebelum switcher
            // di navigasi sempat menetapkannya di request yang sama.
            $owned = ActiveRole::ownedRoleNames($user);
            $peranAktif = count($owned) > 1 ? $owned[0] : implode(', ', $owned);
        }
    }
@endphp

<x-filament-widgets::widget class="fi-account-widget">
    <x-filament::section>
        <x-filament-panels::avatar.user
            size="lg"
            :user="$user"
            loading="lazy"
        />

        <div class="fi-account-widget-main">
            <h2 class="fi-account-widget-heading">
                {{ __('filament-panels::widgets/account-widget.welcome', ['app' => config('app.name')]) }}
            </h2>

            <p class="fi-account-widget-user-name">
                {{ filament()->getUserName($user) }}
            </p>

            @if (filled($peranAktif))
                <p class="fi-account-widget-user-name" style="font-size:.8125rem;font-weight:500;opacity:.7;margin-top:2px;">
                    Anda berperan sebagai {{ $peranAktif }}
                </p>
            @endif
        </div>

        <form
            action="{{ filament()->getLogoutUrl() }}"
            method="post"
            class="fi-account-widget-logout-form"
        >
            @csrf

            <x-filament::button
                color="gray"
                :icon="\Filament\Support\Icons\Heroicon::ArrowLeftEndOnRectangle"
                :icon-alias="\Filament\View\PanelsIconAlias::WIDGETS_ACCOUNT_LOGOUT_BUTTON"
                labeled-from="sm"
                tag="button"
                type="submit"
            >
                {{ __('filament-panels::widgets/account-widget.actions.logout.label') }}
            </x-filament::button>
        </form>
    </x-filament::section>
</x-filament-widgets::widget>
