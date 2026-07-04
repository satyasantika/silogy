<?php

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\States\DraftState;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
});

it('menandai kurikulum belum digunakan bila tidak ada profil lulusan', function () {
    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Kosong',
        'tahun' => 2025,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    expect($kurikulum->belumDigunakanDiTabelLain())->toBeTrue();
});

it('menandai kurikulum sudah digunakan bila ada profil lulusan', function () {
    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Terisi',
        'tahun' => 2025,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-01',
        'deskripsi' => 'Profil uji',
    ]);

    expect($kurikulum->fresh()->belumDigunakanDiTabelLain())->toBeFalse();
});
