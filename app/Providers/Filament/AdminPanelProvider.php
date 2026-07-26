<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\WelcomeWidget;
use App\Models\User;
use App\Modules\Auth\Filament\Pages\PilihPeranUnit;
use App\Modules\Auth\Support\ActiveRole;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
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
            ->homeUrl('/dashboard')
            ->brandName('SILOGY')
            ->colors([
                'primary' => Color::hex('#1e3a5f'),
            ])
            ->darkMode(condition: true, isForced: false)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                NavigationGroup::make('Institusi'),
                NavigationGroup::make('Autentikasi'),
                NavigationGroup::make('Mahasiswa'),
                NavigationGroup::make('Kurikulum'),
                NavigationGroup::make('Mata Kuliah'),
                NavigationGroup::make('Penilaian'),
                NavigationGroup::make('Interaksi'),
                NavigationGroup::make('AI Analisis'),
                NavigationGroup::make('Laporan'),
                NavigationGroup::make('Audit'),
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => Blade::render("@livewire('silogy.role-switcher')"),
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_FOOTER,
                function (): string {
                    $user = auth()->user();

                    if (! $user instanceof User || count(ActiveRole::ownedRoleNames($user)) <= 1) {
                        return '';
                    }

                    return Blade::render(
                        '<x-filament::icon-button icon="heroicon-o-arrows-right-left" tag="a" :href="$url" label="Ganti peran & unit" tooltip="Ganti peran & unit" />',
                        ['url' => PilihPeranUnit::getUrl()],
                    );
                },
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
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->discoverWidgets(
                in: app_path('Modules/Kalkulasi/Filament/Widgets'),
                for: 'App\Modules\Kalkulasi\Filament\Widgets',
            )
            ->widgets([
                WelcomeWidget::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
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
