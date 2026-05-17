<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Auth\Policies\UserPolicy;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Policies\AcademicUnitPolicy;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Support\Facades\Gate;
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
        Gate::policy(AcademicUnit::class, AcademicUnitPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }
}
