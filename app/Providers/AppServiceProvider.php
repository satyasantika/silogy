<?php

namespace App\Providers;

use App\Auth\Http\Responses\FilamentDefaultLoginRedirect;
use App\Models\Permission;
use App\Models\User;
use App\Modules\AI\Models\AnalisisAi;
use App\Modules\AI\Policies\AnalisisAiPolicy;
use App\Modules\AI\RateLimiters\GeminiPerUserPerDay;
use App\Modules\Audit\Models\Activity;
use App\Modules\Audit\Policies\ActivityLogPolicy;
use App\Modules\Auth\Livewire\PeranUnitMenu;
use App\Modules\Auth\Livewire\RoleSwitcher;
use App\Modules\Auth\Policies\PermissionPolicy;
use App\Modules\Auth\Policies\UserPolicy;
use App\Modules\BoK\Models\Bok;
use App\Modules\BoK\Policies\BokPolicy;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Policies\CplPolicy;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Institusi\Observers\AcademicUnitUserObserver;
use App\Modules\Institusi\Policies\AcademicUnitPolicy;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Policies\KelasMkPolicy;
use App\Modules\Kurikulum\Listeners\LogStateTransition;
use App\Modules\Kurikulum\Listeners\SyncKurikulumStateSubscriber;
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
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Observers\NilaiMahasiswaObserver;
use App\Modules\Penilaian\Observers\SubcpmkKomponenPenilaianObserver;
use App\Modules\Penilaian\Policies\InputNilaiPolicy;
use App\Modules\Penilaian\Policies\KomponenPenilaianPolicy;
use App\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Actions\Action;
use Filament\Actions\AttachAction;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as FilamentLoginResponseContract;
use Filament\Auth\Http\Responses\LoginResponse as FilamentVendorLoginResponse;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPasswordNotification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Spatie\ModelStates\Events\StateChanged;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FilamentLoginResponseContract::class, FilamentDefaultLoginRedirect::class);
        $this->app->bind(FilamentVendorLoginResponse::class, FilamentDefaultLoginRedirect::class);
        $this->app->bind(FilamentResetPasswordNotification::class, ResetPasswordNotification::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::component('silogy.role-switcher', RoleSwitcher::class);
        Livewire::component('silogy.peran-unit-menu', PeranUnitMenu::class);
        Livewire::component('silogy.kurikulum-terpilih-banner', \App\Modules\Kurikulum\Livewire\KurikulumTerpilihBanner::class);

        $this->configureFilamentActionIcons();

        RateLimiter::for('health', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for(GeminiPerUserPerDay::rateLimiterName(), function (Request $request): Limit {
            return Limit::perDay(GeminiPerUserPerDay::MAX_ATTEMPTS)
                ->by($request->user()?->id ?? $request->ip());
        });

        Gate::policy(AnalisisAi::class, AnalisisAiPolicy::class);
        Gate::policy(Activity::class, ActivityLogPolicy::class);
        Gate::policy(AcademicUnit::class, AcademicUnitPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
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

        Gate::define('inputNilai', fn (User $user, KelasMk $kelasMk): bool => app(InputNilaiPolicy::class)->inputNilai($user, $kelasMk));

        Event::listen(StateChanged::class, LogStateTransition::class);
        Event::subscribe(SyncKurikulumStateSubscriber::class);

        NilaiMahasiswa::observe(NilaiMahasiswaObserver::class);
        SubcpmkKomponenPenilaian::observe(SubcpmkKomponenPenilaianObserver::class);
        AcademicUnitUser::observe(AcademicUnitUserObserver::class);

        $this->configureUrlForSubpathDeployment();
    }

    /**
     * Di belakang reverse proxy Apache (mis. supportfkip.unsil.ac.id/demo-silogy),
     * Laravel hanya melihat host:port internal — bukan skema asli (https) ataupun
     * prefix path (/demo-silogy) dari APP_URL. Tanpa ini, route()/url()/redirect()
     * akan menghasilkan URL tanpa prefix tersebut, memutus login redirect, form
     * action, dsb. Tidak berefek apa pun bila APP_URL tidak berisi path (dev lokal).
     */
    protected function configureUrlForSubpathDeployment(): void
    {
        $subPath = rtrim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');

        // Tanpa path di APP_URL (dev lokal, atau akses lewat port apa pun),
        // biarkan Laravel pakai deteksi host dari request seperti biasa — kalau
        // forceRootUrl() dipaksa ke sini tanpa syarat, URL yang dihasilkan akan
        // selalu memakai host:port APP_URL walau browser sedang mengakses lewat
        // port lain (mis. beberapa proyek berbagi satu docker-compose dengan
        // port yang di-remap), sehingga tombol login/redirect malah menuju host
        // yang salah.
        if ($subPath === '') {
            return;
        }

        if (parse_url((string) config('app.url'), PHP_URL_SCHEME) === 'https') {
            URL::forceScheme('https');
        }

        URL::forceRootUrl(config('app.url'));

        // FrontendAssets::js() (script Livewire inti) membaca URI route mentah,
        // bukan lewat route()/url() — jadi tidak ikut ter-prefix oleh
        // forceRootUrl() di atas dan harus dipatch manual di sini. Route
        // AJAX (/livewire/update) TIDAK disentuh karena sudah resolve lewat
        // UrlGenerator::toRoute(), yang sudah menghormati forceRootUrl().
        Livewire::setScriptRoute(fn ($handle) => Route::get(
            $subPath.(config('app.debug') ? '/livewire/livewire.js' : '/livewire/livewire.min.js'),
            $handle,
        ));
    }

    /**
     * Icon bawaan agar semua tombol aksi Filament (termasuk header action
     * yang default-nya tanpa icon) selalu tampil ber-icon.
     */
    protected function configureFilamentActionIcons(): void
    {
        CreateAction::configureUsing(fn (CreateAction $action) => $action->icon(Heroicon::Plus));
        EditAction::configureUsing(fn (EditAction $action) => $action->icon(Heroicon::PencilSquare));
        DeleteAction::configureUsing(fn (DeleteAction $action) => $action->icon(Heroicon::Trash));
        ViewAction::configureUsing(fn (ViewAction $action) => $action->icon(Heroicon::Eye));
        AttachAction::configureUsing(fn (AttachAction $action) => $action->icon(Heroicon::Link));

        // Tombol submit/batal pada footer modal (konfirmasi, form action, dsb.)
        // tidak ber-icon secara bawaan — pasang icon representatif untuk semuanya.
        Action::configureUsing(function (Action $action): void {
            $action->modalSubmitAction(fn (Action $action): Action => $action->icon(Heroicon::Check));
            $action->modalCancelAction(fn (Action $action): Action => $action->icon(Heroicon::XMark));
        });
    }
}
