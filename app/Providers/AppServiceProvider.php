<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Auth\Policies\UserPolicy;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Policies\AcademicUnitPolicy;
use App\Modules\Kurikulum\Listeners\LogStateTransition;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Policies\KurikulumPolicy;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Mahasiswa\Policies\MahasiswaPolicy;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\ModelStates\Events\StateChanged;

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
        Gate::policy(Mahasiswa::class, MahasiswaPolicy::class);
        Gate::policy(Kurikulum::class, KurikulumPolicy::class);

        Event::listen(StateChanged::class, LogStateTransition::class);
    }
}
