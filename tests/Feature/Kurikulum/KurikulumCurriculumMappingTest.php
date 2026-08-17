<?php

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\States\BokState;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('kurikulum dapat naik dari bok ke mk ke setdosenmk setelah pemetaan cpl bok dan mk unit', function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $timkur = User::query()->where('username', 'timkur')->first();
    $this->actingAs($timkur);

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Mapping Test',
        'tahun' => 2025,
        'state' => BokState::class,
        'is_active' => false,
    ]);

    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-01',
        'nama' => 'Profil Uji',
        'deskripsi' => 'Deskripsi profil',
    ]);
    ProfilIndikator::query()->create([
        'profil_id' => $profil->id,
        'nama' => 'Indikator',
    ]);

    $cpl1 = Cpl::factory()->forAcademicUnit($prodi)->create(['kode' => 'CPL-01', 'deskripsi' => 'CPL 1']);
    $cpl2 = Cpl::factory()->forAcademicUnit($prodi)->create(['kode' => 'CPL-02', 'deskripsi' => 'CPL 2']);
    CplProfilLulusan::query()->create(['cpl_id' => $cpl1->id, 'profil_lulusan_id' => $profil->id]);
    CplProfilLulusan::query()->create(['cpl_id' => $cpl2->id, 'profil_lulusan_id' => $profil->id]);

    $bok1 = Bok::factory()->forAcademicUnit($prodi)->create(['kode' => 'BOK-01', 'nama' => 'BoK 1']);
    $bok2 = Bok::factory()->forAcademicUnit($prodi)->create(['kode' => 'BOK-02', 'nama' => 'BoK 2']);
    $bok3 = Bok::factory()->forAcademicUnit($prodi)->create(['kode' => 'BOK-03', 'nama' => 'BoK 3']);

    $cb1 = CplBok::query()->create(['cpl_id' => $cpl1->id, 'bok_id' => $bok1->id, 'bobot' => 50]);
    CplBok::query()->create(['cpl_id' => $cpl1->id, 'bok_id' => $bok2->id, 'bobot' => 50]);
    CplBok::query()->create(['cpl_id' => $cpl2->id, 'bok_id' => $bok3->id, 'bobot' => 100]);

    expect($kurikulum->fresh()->state->getValue())->toBe('mk');

    $mks = collect();
    for ($i = 1; $i <= 4; $i++) {
        $mks->push(Mk::factory()->forAcademicUnit($prodi)->create([
            'nama' => "Mata Kuliah {$i}",
            'sks_teori' => 2,
            'sks_praktik' => 1,
            'sks_lapangan' => 0,
            'sks' => 3,
        ]));
    }

    MkUnit::factory()->forMk($mks->first())->forAcademicUnit($prodi)->create([
        'kode' => 'IF1001',
        'semester_ke' => 1,
    ]);

    CplMk::query()->create([
        'cpl_bok_id' => $cb1->id,
        'mk_id' => $mks->first()->id,
        'bobot' => 100,
    ]);

    expect($kurikulum->fresh()->state->getValue())->toBe('setdosenmk');
});
