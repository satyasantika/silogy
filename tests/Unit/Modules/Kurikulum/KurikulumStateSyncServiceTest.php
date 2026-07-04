<?php

use App\Models\User;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\Services\KurikulumStateSyncService;
use App\Modules\Kurikulum\States\CplState;
use App\Modules\Kurikulum\States\DraftState;
use App\Modules\Kurikulum\States\ProfilLulusanState;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
});

it('sinkronisasi state memindahkan draft prodi ke tahap profil lulusan', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Sync',
        'tahun' => 2026,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    app(KurikulumStateSyncService::class)->sync($kurikulum->fresh());

    expect($kurikulum->fresh()->state->getValue())->toBe('profil_lulusan');
});

it('sinkronisasi state memindahkan draft non prodi ke tahap cpl', function () {
    $fak = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $fak->id,
        'nama' => 'Kurikulum Fakultas',
        'tahun' => 2026,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    app(KurikulumStateSyncService::class)->sync($kurikulum->fresh());

    expect($kurikulum->fresh()->state->getValue())->toBe('cpl');
});

it('sinkronisasi state naik ke cpl setelah profil memiliki indikator', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Indikator',
        'tahun' => 2026,
        'state' => ProfilLulusanState::class,
        'is_active' => false,
    ]);

    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-01',
        'nama' => 'Profil uji',
        'deskripsi' => 'Deskripsi',
        'urutan' => 1,
    ]);

    ProfilIndikator::query()->create([
        'profil_id' => $profil->id,
        'nama' => 'Indikator',
        'deskripsi' => 'Deskripsi indikator',
    ]);

    app(KurikulumStateSyncService::class)->sync($kurikulum->fresh());

    expect($kurikulum->fresh()->state->getValue())->toBe('cpl');
});

it('ketersediaan menu menampilkan status ada atau belum', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Menu',
        'tahun' => 2026,
        'state' => CplState::class,
        'is_active' => false,
    ]);

    ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-1',
        'nama' => 'Profil',
        'deskripsi' => 'Profil uji',
        'urutan' => 1,
    ]);

    Cpl::query()->create([
        'academic_unit_id' => $prodi->id,
        'kode' => 'CPL-01',
        'deskripsi' => 'CPL uji',
        'domain' => 'kognitif',
    ]);

    $menu = KurikulumResource::ketersediaanMenu($kurikulum->fresh());

    expect($menu)->toMatchArray([
        'profil' => true,
        'cpl' => true,
        'bok' => false,
        'mk' => false,
    ]);
});

it('navigasi menu kurikulum menyimpan kurikulum terpilih dan mengarah ke cpl', function () {
    $timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Nav',
        'tahun' => 2026,
        'state' => DraftState::class,
        'is_active' => false,
    ]);

    $this->actingAs($timkur)
        ->get(route('silogy.kurikulum-navigasi', ['kurikulum' => $kurikulum->id, 'menu' => 'cpl']))
        ->assertRedirect('/cpls');

    expect(KurikulumTerpilih::currentId())->toBe($kurikulum->id);
});

it('observer cpl profil lulusan memicu sinkronisasi state ke cpl', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Observer',
        'tahun' => 2026,
        'state' => ProfilLulusanState::class,
        'is_active' => false,
    ]);

    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-01',
        'nama' => 'Profil observer',
        'deskripsi' => 'Deskripsi',
        'urutan' => 1,
    ]);

    ProfilIndikator::query()->create([
        'profil_id' => $profil->id,
        'nama' => 'Indikator',
        'deskripsi' => 'Deskripsi indikator',
    ]);

    expect($kurikulum->fresh()->state->getValue())->toBe('cpl');
});

it('observer pivot cpl profil lulusan ikut memicu sinkronisasi state', function () {
    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Pivot',
        'tahun' => 2026,
        'state' => CplState::class,
        'is_active' => false,
    ]);

    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-01',
        'nama' => 'Profil pivot',
        'deskripsi' => 'Deskripsi',
        'urutan' => 1,
    ]);

    $cpl = Cpl::query()->create([
        'academic_unit_id' => $prodi->id,
        'kode' => 'CPL-01',
        'deskripsi' => 'CPL pivot',
        'domain' => 'kognitif',
    ]);

    CplProfilLulusan::query()->create([
        'cpl_id' => $cpl->id,
        'profil_lulusan_id' => $profil->id,
    ]);

    expect($kurikulum->fresh()->state->getValue())->toBe('bok');
});
