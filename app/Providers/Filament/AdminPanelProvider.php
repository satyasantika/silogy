<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->passwordReset()
            ->brandName('SILOGY')
            ->colors([
                'primary' => Color::hex('#1e3a5f'),
            ])
            ->darkMode(condition: true, isForced: false)
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
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
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
