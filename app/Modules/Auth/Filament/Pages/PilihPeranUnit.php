<?php

namespace App\Modules\Auth\Filament\Pages;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Auth\Support\PeranUnitFormFields;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * Gerbang "Pilih Peran & Unit" — kelola peran/unit aktif untuk user
 * multi-role (atau single-role dengan banyak unit). Dipakai saat login,
 * impersonate, dan ganti peran dari menu pengguna.
 */
class PilihPeranUnit extends Page
{
    protected string $view = 'filament.modules.auth.pages.pilih-peran-unit';

    protected static ?string $slug = 'pilih-peran-unit';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Pilih Peran & Unit';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            redirect()->intended(Dashboard::getUrl());

            return;
        }

        $roles = ActiveRole::ownedRoleNames($user);

        if (count($roles) <= 1) {
            $singleRole = $roles[0] ?? null;
            $hasUnitChoice = $singleRole !== null
                && PeranUnitFormFields::unitCountForRole($user, $singleRole) > 1;

            if (! $hasUnitChoice) {
                redirect()->intended(Dashboard::getUrl());

                return;
            }
        }

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $user = auth()->user();

        return $schema
            ->components($user instanceof User ? PeranUnitFormFields::schema($user) : [])
            ->statePath('data');
    }

    public function submit(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        PeranUnitFormFields::apply($this->form->getState());

        redirect()->intended(Dashboard::getUrl());
    }

    public function isImpersonating(): bool
    {
        return app(ImpersonateManager::class)->isImpersonating();
    }

    public function leaveImpersonate(): void
    {
        $manager = app(ImpersonateManager::class);

        if (! $manager->isImpersonating()) {
            return;
        }

        $manager->leave();

        session()->forget(ActiveRole::SESSION_KEY);
        session()->forget(AcademicUnitTerpilih::SESSION_KEY);

        $this->redirect(url('/dashboard'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $unitAktifId = AcademicUnitTerpilih::currentId($user);

        return [
            'user' => $user,
            'peranAktif' => ActiveRole::currentFor($user),
            'unitAktifNama' => $unitAktifId
                ? AcademicUnit::query()->find($unitAktifId)?->nama
                : null,
        ];
    }
}
