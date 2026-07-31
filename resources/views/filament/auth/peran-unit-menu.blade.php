{{--
    Footer sidebar (satu baris): menu identitas | tombol Keluar.
    Saat sidebar diminimize hanya Keluar yang tampil.
--}}
@php
    use App\Modules\Institusi\Models\AcademicUnit;

    $unitNama = $unitAktifId ? AcademicUnit::find($unitAktifId)?->nama : null;
@endphp

<div
    class="fi-sidebar-footer silogy-sidebar-user"
    x-data="{}"
>
    <div
        class="silogy-sidebar-user-row"
        style="display:flex;align-items:center;gap:8px;padding:4px 0;min-width:0;width:100%;"
        x-bind:class="{ 'silogy-sidebar-user-row--collapsed': ! $store.sidebar.isOpen }"
    >
        <div
            class="silogy-sidebar-user-identity"
            x-show="$store.sidebar.isOpen"
            x-cloak
            style="flex:1;min-width:0;"
        >
            <x-filament::dropdown placement="top-start" teleport>
                <x-slot name="trigger">
                    <button
                        type="button"
                        aria-label="Menu pengguna"
                        class="fi-user-menu-trigger"
                        style="width:100%;justify-content:flex-start;"
                    >
                        <x-filament-panels::avatar.user size="md" :user="$user" loading="lazy" />

                        <span class="fi-user-menu-trigger-text" style="min-width:0;text-align:start;">
                            <span class="silogy-sidebar-user-name" style="display:block;font-weight:600;font-size:.875rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                {{ $user ? filament()->getUserName($user) : '' }}
                            </span>
                            @if (count($roles) === 0)
                                <span class="fi-color-danger fi-text-color-600" style="display:block;font-size:.7rem;font-weight:600;">
                                    Belum ada peran/unit
                                </span>
                            @else
                                <span class="silogy-sidebar-user-meta" style="display:block;font-size:.7rem;opacity:.75;min-width:0;">
                                    <span
                                        class="silogy-sidebar-user-peran"
                                        style="display:block;white-space:normal;overflow-wrap:break-word;"
                                    >
                                        {{ $peranAktif }}
                                    </span>
                                    @if (filled($unitNama))
                                        <span
                                            class="silogy-sidebar-user-unit"
                                            style="display:block;white-space:normal;overflow-wrap:break-word;"
                                        >
                                            {{ $unitNama }}
                                        </span>
                                    @endif
                                </span>
                            @endif
                        </span>

                        {{
                            \Filament\Support\generate_icon_html(
                                \Filament\Support\Icons\Heroicon::ChevronUp,
                                alias: \Filament\View\PanelsIconAlias::USER_MENU_TOGGLE_BUTTON,
                            )
                        }}
                    </button>
                </x-slot>

                <x-filament::dropdown.header icon="heroicon-m-user-circle">
                    {{ $user ? filament()->getUserName($user) : '' }}
                </x-filament::dropdown.header>

                @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
                    <x-filament::dropdown.list>
                        <x-filament-panels::theme-switcher />
                    </x-filament::dropdown.list>
                @endif

                <x-filament::dropdown.list>
                    <x-filament::dropdown.list.item
                        tag="a"
                        :href="$profileUrl"
                        icon="heroicon-m-user-circle"
                    >
                        Profil
                    </x-filament::dropdown.list.item>

                    <x-filament::dropdown.list.item
                        wire:click="mountAction('gantiPassword')"
                        icon="heroicon-m-key"
                        x-on:click="close()"
                    >
                        Ganti kata sandi
                    </x-filament::dropdown.list.item>

                    @if ($bisaGanti)
                        <x-filament::dropdown.list.item
                            tag="a"
                            :href="$pilihPeranUnitUrl"
                            icon="heroicon-m-arrows-right-left"
                        >
                            Ganti peran & unit
                        </x-filament::dropdown.list.item>
                    @endif
                </x-filament::dropdown.list>
            </x-filament::dropdown>
        </div>

        <div
            class="silogy-sidebar-user-actions"
            style="display:flex;align-items:center;gap:4px;flex-shrink:0;"
            x-bind:style="! $store.sidebar.isOpen ? 'margin-inline:auto;' : null"
        >
            {{ $this->keluarAction() }}
        </div>
    </div>

    <x-filament-actions::modals />
</div>
