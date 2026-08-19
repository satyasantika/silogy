<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Services\KurikulumStateSyncService;

trait MvpEndToEndHelpers
{
    /**
     * @var list<string>
     */
    protected array $urutanStateKurikulum = [
        'draft',
        'profil_lulusan',
        'cpl',
        'bok',
        'mk',
        'setdosenmk',
        'aktif',
    ];

    protected function loginUser(string $username): User
    {
        $user = User::query()->where('username', $username)->firstOrFail();
        $this->actingAs($user);

        return $user;
    }

    protected function buatUnitE2e(AcademicUnit $univ): AcademicUnit
    {
        $fak = AcademicUnit::factory()->faculty($univ)->create([
            'code' => 'E2E-FT',
            'nama' => 'Fakultas E2E',
            'status' => 'aktif',
        ]);

        $jur = AcademicUnit::factory()->department($fak)->create([
            'code' => 'E2E-INF',
            'nama' => 'Jurusan E2E',
            'status' => 'aktif',
        ]);

        return AcademicUnit::factory()->studyProgram($jur)->create([
            'code' => 'E2E-SI',
            'nama' => 'S1 Sistem Informasi E2E',
            'kode_pddikti' => '57299',
            'jenjang' => 'S1',
            'gelar_lulusan' => 'S.Kom.',
            'status' => 'aktif',
        ]);
    }

    protected function lanjutkanKurikulumKe(Kurikulum $kurikulum, string $stateClass): void
    {
        app(KurikulumStateSyncService::class)->sync($kurikulum->fresh());
        $kurikulum->refresh();

        $targetName = $stateClass::$name;
        $currentValue = $kurikulum->state->getValue();

        $currentIdx = array_search($currentValue, $this->urutanStateKurikulum, true);
        $targetIdx = array_search($targetName, $this->urutanStateKurikulum, true);

        if ($currentIdx !== false && $targetIdx !== false && $currentIdx >= $targetIdx) {
            return;
        }

        expect($kurikulum->state->canTransitionTo($stateClass))->toBeTrue(
            "Tidak dapat transisi dari {$currentValue} ke {$targetName}",
        );

        $kurikulum->state->transitionTo($stateClass);
        $kurikulum->refresh();
    }
}
