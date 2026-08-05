<?php

namespace App\Modules\Auth\Filament\Pages;

use App\Filament\Pages\Dashboard;
use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Auth\Support\PeranUnitFormFields;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Support\AcademicUnitTerpilih;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Lab404\Impersonate\Services\ImpersonateManager;

/**
 * Gerbang "Pilih Peran & Unit" — kelola peran/unit aktif untuk user
 * multi-role (atau single-role dengan banyak unit). Dipakai saat login,
 * impersonate, dan ganti peran dari menu pengguna.
 *
 * UX: klik kartu persegi untuk memilih peran (dan unit bila perlu);
 * tanpa tombol "Lanjutkan".
 */
class PilihPeranUnit extends Page
{
    protected string $view = 'filament.modules.auth.pages.pilih-peran-unit';

    protected static ?string $slug = 'pilih-peran-unit';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Pilih Peran & Unit';

    public ?string $selectedRole = null;

    /**
     * Opsi unit akademik setelah peran yang butuh pilihan unit dipilih.
     *
     * @var array<int, array{id: string, nama: string, type: string}>
     */
    public array $unitOptions = [];

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirectIntended(Dashboard::getUrl());

            return;
        }

        $roles = ActiveRole::ownedRoleNames($user);

        if (count($roles) <= 1) {
            $singleRole = $roles[0] ?? null;
            $hasUnitChoice = $singleRole !== null
                && PeranUnitFormFields::roleMembutuhkanPilihanUnit($user, $singleRole);

            if (! $hasUnitChoice) {
                $this->redirectIntended(Dashboard::getUrl());
            }
        }
    }

    public function pilihPeran(string $role): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return;
        }

        $roles = ActiveRole::ownedRoleNames($user);

        if (! in_array($role, $roles, true)) {
            return;
        }

        if (! PeranUnitFormFields::roleMembutuhkanPilihanUnit($user, $role)) {
            PeranUnitFormFields::apply([
                'role' => $role,
                'unit_id' => PeranUnitFormFields::unitIdUntukRole($user, $role),
            ]);

            $this->redirectIntended(Dashboard::getUrl());

            return;
        }

        $unitIds = PeranUnitFormFields::unitIdsForRole($user, $role);

        $this->selectedRole = $role;
        $this->unitOptions = AcademicUnit::query()
            ->whereIn('id', $unitIds)
            ->orderBy('nama')
            ->get(['id', 'nama', 'type', 'jenjang'])
            ->map(fn (AcademicUnit $unit): array => [
                'id' => $unit->id,
                'nama' => $unit->nama_lengkap,
                'type' => (string) $unit->type,
            ])
            ->all();
    }

    public function pilihUnit(string $unitId): void
    {
        $user = auth()->user();

        if (! $user instanceof User || blank($this->selectedRole)) {
            return;
        }

        $unitIds = PeranUnitFormFields::unitIdsForRole($user, $this->selectedRole);

        if (! $unitIds->contains($unitId)) {
            return;
        }

        PeranUnitFormFields::apply([
            'role' => $this->selectedRole,
            'unit_id' => $unitId,
        ]);

        $this->redirectIntended(Dashboard::getUrl());
    }

    public function kembaliKePeran(): void
    {
        $this->selectedRole = null;
        $this->unitOptions = [];
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
                ? AcademicUnit::query()->find($unitAktifId)?->nama_lengkap
                : null,
            'roles' => collect(ActiveRole::ownedRoleNames($user))
                ->map(fn (string $role): array => [
                    'name' => $role,
                    'icon' => PeranUnitFormFields::iconForRole($role),
                    'color' => PeranUnitFormFields::colorForRole($role),
                ])
                ->all(),
            'unitIcon' => Heroicon::OutlinedBuildingOffice2,
        ];
    }
}
