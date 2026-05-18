<?php

namespace App\Providers;

use App\Models\User;
use App\Modules\Auth\Policies\UserPolicy;
use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Policies\BokPolicy;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Policies\CplPolicy;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Policies\AcademicUnitPolicy;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Policies\KelasMkPolicy;
use App\Modules\Kurikulum\Listeners\LogStateTransition;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Policies\KurikulumPolicy;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Mahasiswa\Policies\MahasiswaPolicy;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Policies\CpmkPolicy;
use App\Modules\MK\Policies\MkPolicy;
use App\Modules\MK\Policies\MkUnitPolicy;
use App\Modules\MK\Policies\SubcpmkPolicy;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Policies\KomponenPenilaianPolicy;
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
        Gate::policy(Cpl::class, CplPolicy::class);
        Gate::policy(Bok::class, BokPolicy::class);
        Gate::policy(Mk::class, MkPolicy::class);
        Gate::policy(MkUnit::class, MkUnitPolicy::class);
        Gate::policy(KelasMk::class, KelasMkPolicy::class);
        Gate::policy(Cpmk::class, CpmkPolicy::class);
        Gate::policy(Subcpmk::class, SubcpmkPolicy::class);
        Gate::policy(KomponenPenilaian::class, KomponenPenilaianPolicy::class);

        Event::listen(StateChanged::class, LogStateTransition::class);
    }
}
