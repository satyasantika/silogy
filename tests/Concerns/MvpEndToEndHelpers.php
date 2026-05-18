<?php

namespace Tests\Concerns;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;

trait MvpEndToEndHelpers
{
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
        expect($kurikulum->state->canTransitionTo($stateClass))->toBeTrue(
            "Tidak dapat transisi dari {$kurikulum->state->getValue()} ke {$stateClass::$name}",
        );

        $kurikulum->state->transitionTo($stateClass);
        $kurikulum->refresh();
    }

}
