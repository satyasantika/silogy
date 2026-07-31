<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\WelcomeWidget;
use App\Models\User;
use App\Modules\Auth\Filament\Actions\GantiPasswordAction;
use App\Modules\Auth\Filament\Actions\KeluarAction;
use App\Modules\Auth\Filament\Pages\PilihPeranUnit;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Auth\Support\PeranUnitFormFields;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login(Login::class)
            ->passwordReset()
            ->profile(EditProfile::class)
            ->homeUrl('/dashboard')
            ->brandName('SILOGY')
            ->brandLogo(fn () => view('filament.components.brand-logo'))
            ->brandLogoHeight('auto')
            ->favicon(asset('favicon.ico'))
            ->colors([
                'primary' => Color::hex('#009900'),
            ])
            ->darkMode(condition: true, isForced: false)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->userMenuItems([
                Action::make('gantiPeranUnit')
                    ->label('Ganti peran & unit')
                    ->icon(Heroicon::ArrowsRightLeft)
                    ->url(fn (): string => PilihPeranUnit::getUrl())
                    ->visible(function (): bool {
                        $user = auth()->user();

                        if (! $user instanceof User) {
                            return false;
                        }

                        $roles = ActiveRole::ownedRoleNames($user);
                        $peranAktif = PeranUnitFormFields::defaultRole($user);

                        return count($roles) > 1
                            || PeranUnitFormFields::unitCountForRole($user, $peranAktif) > 1;
                    })
                    ->sort(5),
                fn (): Action => GantiPasswordAction::make()
                    ->sort(10),
                'logout' => fn (): Action => KeluarAction::make('logout')
                    ->label(__('filament-panels::layout.actions.logout.label'))
                    ->sort(PHP_INT_MAX),
            ])
            ->navigationGroups([
                NavigationGroup::make('Institusi'),
                NavigationGroup::make('Autentikasi'),
                NavigationGroup::make('Mahasiswa'),
                NavigationGroup::make('Kurikulum'),
                NavigationGroup::make('Mata Kuliah'),
                NavigationGroup::make('Penilaian'),
                NavigationGroup::make('Pengampu MK'),
                NavigationGroup::make('Interaksi'),
                NavigationGroup::make('AI Analisis'),
                NavigationGroup::make('Laporan'),
                NavigationGroup::make('Audit'),
            ])
            // RoleSwitcher headless: auto-set role aktif, tanpa ikon di nav.
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => Blade::render("@livewire('silogy.role-switcher')"),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                fn (): string => Blade::render("@livewire('silogy.peran-unit-menu')"),
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => view('filament.hooks.sidebar-user-menu-styles')->render()
                    .view('filament.hooks.brand-logo-styles')->render()
                    .view('filament.hooks.kurikulum-cards-styles')->render()
                    .view('filament.hooks.keluar-modal-styles')->render()
                    .view('filament.hooks.unsil-theme-styles')->render()
                    .view('filament.hooks.status-badge-styles')->render(),
            )
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn (): string => view('filament.hooks.footer')->render(),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverResources(
                in: app_path('Modules/Institusi/Filament/Resources'),
                for: 'App\Modules\Institusi\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Auth/Filament/Resources'),
                for: 'App\Modules\Auth\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Mahasiswa/Filament/Resources'),
                for: 'App\Modules\Mahasiswa\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Kurikulum/Filament/Resources'),
                for: 'App\Modules\Kurikulum\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/CPL/Filament/Resources'),
                for: 'App\Modules\CPL\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/BoK/Filament/Resources'),
                for: 'App\Modules\BoK\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/MK/Filament/Resources'),
                for: 'App\Modules\MK\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Kelas/Filament/Resources'),
                for: 'App\Modules\Kelas\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Penilaian/Filament/Resources'),
                for: 'App\Modules\Penilaian\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/Audit/Filament/Resources'),
                for: 'App\Modules\Audit\Filament\Resources',
            )
            ->discoverResources(
                in: app_path('Modules/AI/Filament/Resources'),
                for: 'App\Modules\AI\Filament\Resources',
            )
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverPages(
                in: app_path('Modules/Penilaian/Filament/Pages'),
                for: 'App\Modules\Penilaian\Filament\Pages',
            )
            ->discoverPages(
                in: app_path('Modules/AI/Filament/Pages'),
                for: 'App\Modules\AI\Filament\Pages',
            )
            ->discoverPages(
                in: app_path('Modules/Kurikulum/Filament/Pages'),
                for: 'App\Modules\Kurikulum\Filament\Pages',
            )
            ->discoverPages(
                in: app_path('Modules/MK/Filament/Pages'),
                for: 'App\Modules\MK\Filament\Pages',
            )
            ->pages([
                Dashboard::class,
                PilihPeranUnit::class,
            ])
            // Semua direktori widget WAJIB di-discover walau widgetnya
            // dipasang manual di Dashboard::content()/getWidgets(): panel-lah
            // yang mendaftarkan alias Livewire tiap widget (Panel::
            // registerLivewireComponents), dan tanpa itu request
            // /livewire/update dari widget (polling, wire:model.live, aksi)
            // gagal dengan ComponentNotFoundException.
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->discoverWidgets(
                in: app_path('Modules/AI/Filament/Widgets'),
                for: 'App\Modules\AI\Filament\Widgets',
            )
            ->discoverWidgets(
                in: app_path('Modules/Kalkulasi/Filament/Widgets'),
                for: 'App\Modules\Kalkulasi\Filament\Widgets',
            )
            ->discoverWidgets(
                in: app_path('Modules/Kurikulum/Filament/Widgets'),
                for: 'App\Modules\Kurikulum\Filament\Widgets',
            )
            ->discoverWidgets(
                in: app_path('Modules/MK/Filament/Widgets'),
                for: 'App\Modules\MK\Filament\Widgets',
            )
            ->discoverWidgets(
                in: app_path('Modules/Penilaian/Filament/Widgets'),
                for: 'App\Modules\Penilaian\Filament\Widgets',
            )
            ->widgets([
                WelcomeWidget::class,
            ])
            ->plugins([
                // Peran hanya untuk Super Admin — tampil datar tanpa header grup.
                FilamentShieldPlugin::make()->navigationGroup(null),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
