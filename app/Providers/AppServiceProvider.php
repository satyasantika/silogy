<?php

namespace App\Providers;

use App\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FilamentResetPassword::class, ResetPasswordNotification::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
