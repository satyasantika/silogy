<?php

use App\Filament\Pages\Dashboard;
use App\Models\Role;
use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\CreateUser;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\CPL\Models\CplProfilLulusan;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Policies\AcademicUnitPolicy;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kalkulasi\Filament\Widgets\CplUnitChartWidget;
use App\Modules\Kalkulasi\Jobs\RecalkulasiCplJob;
use App\Modules\Kalkulasi\Models\HasilCplMk;
use App\Modules\Kalkulasi\Models\HasilCplMkUnit;
use App\Modules\Kalkulasi\Models\HasilCplUnit;
use App\Modules\Kalkulasi\Models\HasilCpmk;
use App\Modules\Kalkulasi\Models\HasilSubcpmk;
use App\Modules\Kalkulasi\Services\DashboardCplDataService;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\CreateKelasMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\CreateKurikulum;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilIndikator;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Modules\Kurikulum\States\AktifState;
use App\Modules\Kurikulum\States\BokState;
use App\Modules\Kurikulum\States\CplState;
use App\Modules\Kurikulum\States\MkState;
use App\Modules\Kurikulum\States\ProfilLulusanState;
use App\Modules\Kurikulum\States\SetdosenmkState;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Filament\Resources\CpmkResource\Pages\CreateCpmk;
use App\Modules\MK\Filament\Resources\SubcpmkResource\Pages\CreateSubcpmk;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\MvpEndToEndHelpers;

uses(RefreshDatabase::class, MvpEndToEndHelpers::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);
});

it('completes full mvp journey for prodi', function () {
    $startedAt = microtime(true);

    $univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $superadmin = User::query()->where('username', 'superadmin')->firstOrFail();

    // 1. Login superadmin → buat hierarki unit E2E
    Livewire::test(Login::class)
        ->fillForm([
            'email' => 'superadmin@silogy.test',
            'password' => 'siliwangi',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors();

    expect(auth()->user()?->username)->toBe('superadmin');

    $policy = app(AcademicUnitPolicy::class);
    expect($policy->createUnit($superadmin, 'faculty', $univ->id))->toBeTrue();

    $prodi = $this->buatUnitE2e($univ);

    // 2. Pengaturan pengguna eksklusif superadmin → buat pengguna operasional E2E
    $pembuatUser = $this->loginUser('superadmin');

    $roleDosen = Role::query()->where('name', 'Dosen Pengampu')->value('id');
    $roleKorma = Role::query()->where('name', 'Koordinator Mata Kuliah')->value('id');
    $roleTimkur = Role::query()->where('name', 'Tim Kurikulum')->value('id');
    $roleAdminProdi = Role::query()->where('name', 'Admin')->value('id');
    $roleKaprodi = Role::query()->where('name', 'Pimpinan')->value('id');

    foreach ([
        ['username' => 'dosenE2E', 'full_name' => 'Dosen E2E', 'role' => $roleDosen, 'tim_kur' => false, 'pimpinan' => false, 'jabatan' => 'Dosen'],
        ['username' => 'kormaE2E', 'full_name' => 'Koordinator MK E2E', 'role' => $roleKorma, 'tim_kur' => false, 'pimpinan' => false, 'jabatan' => 'Koordinator MK'],
        ['username' => 'timkurE2E', 'full_name' => 'Tim Kurikulum E2E', 'role' => $roleTimkur, 'tim_kur' => true, 'pimpinan' => false, 'jabatan' => 'Anggota Tim Kurikulum'],
        ['username' => 'adminprodiE2E', 'full_name' => 'Admin Prodi E2E', 'role' => $roleAdminProdi, 'tim_kur' => false, 'pimpinan' => false, 'jabatan' => 'Admin'],
        ['username' => 'kaprodiE2E', 'full_name' => 'Kaprodi E2E', 'role' => $roleKaprodi, 'tim_kur' => false, 'pimpinan' => true, 'jabatan' => 'Ketua Program Studi'],
    ] as $akun) {
        Livewire::actingAs($pembuatUser)
            ->test(CreateUser::class)
            ->fillForm([
                'full_name' => $akun['full_name'],
                'username' => $akun['username'],
                'email' => $akun['username'].'@silogy.test',
                'password' => 'siliwangi',
                'roles' => [$akun['role']],
                'academicUnitUsers' => [
                    [
                        'academic_unit_id' => $prodi->id,
                        'status_pimpinan' => $akun['pimpinan'],
                        'status_tim_kurikulum' => $akun['tim_kur'],
                        'jabatan' => $akun['jabatan'],
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();
    }

    $dosenE2E = User::query()->where('username', 'dosenE2E')->firstOrFail();
    $kormaE2E = User::query()->where('username', 'kormaE2E')->firstOrFail();
    $timkurE2E = User::query()->where('username', 'timkurE2E')->firstOrFail();
    $adminprodiE2E = User::query()->where('username', 'adminprodiE2E')->firstOrFail();
    $kaprodiE2E = User::query()->where('username', 'kaprodiE2E')->firstOrFail();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    // 3. Tim kurikulum → kurikulum lengkap hingga state mk
    $this->actingAs($timkurE2E);

    Livewire::test(CreateKurikulum::class)
        ->fillForm([
            'academic_unit_id' => $prodi->id,
            'nama' => 'Kurikulum E2E 2025',
            'kode' => 'KUR-E2E-2025',
            'tahun' => 2025,
            'target_capaian_lulusan' => 75,
            'deskripsi' => 'Kurikulum uji end-to-end MVP',
            'is_active' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $kurikulum = Kurikulum::query()->where('kode', 'KUR-E2E-2025')->firstOrFail();
    expect($kurikulum->state->getValue())->toBe('profil_lulusan');

    $profil = ProfilLulusan::query()->create([
        'kurikulum_id' => $kurikulum->id,
        'kode' => 'PL-E2E-01',
        'nama' => 'Software Engineer E2E',
        'deskripsi' => 'Profil lulusan uji E2E',
        'urutan' => 1,
    ]);

    ProfilIndikator::query()->create([
        'profil_id' => $profil->id,
        'nama' => 'Menguasai rekayasa perangkat lunak',
        'deskripsi' => 'Indikator E2E',
    ]);

    $this->lanjutkanKurikulumKe($kurikulum, ProfilLulusanState::class);

    $cpl1 = Cpl::query()->create([
        'academic_unit_id' => $prodi->id,
        'kode' => 'CPL-E2E-01',
        'deskripsi' => 'CPL E2E pertama',
        'domain' => 'kognitif',
    ]);
    $cpl2 = Cpl::query()->create([
        'academic_unit_id' => $prodi->id,
        'kode' => 'CPL-E2E-02',
        'deskripsi' => 'CPL E2E kedua',
        'domain' => 'afektif',
    ]);

    CplProfilLulusan::query()->create(['cpl_id' => $cpl1->id, 'profil_lulusan_id' => $profil->id]);
    CplProfilLulusan::query()->create(['cpl_id' => $cpl2->id, 'profil_lulusan_id' => $profil->id]);

    $this->lanjutkanKurikulumKe($kurikulum, CplState::class);

    $bok1 = Bok::factory()->forAcademicUnit($prodi)->create(['kode' => 'BOK-E2E-01', 'nama' => 'BoK Algoritma E2E']);
    $bok2 = Bok::factory()->forAcademicUnit($prodi)->create(['kode' => 'BOK-E2E-02', 'nama' => 'BoK Basis Data E2E']);

    $cplBok1 = CplBok::query()->create(['cpl_id' => $cpl1->id, 'bok_id' => $bok1->id, 'bobot' => 100]);
    CplBok::query()->create(['cpl_id' => $cpl2->id, 'bok_id' => $bok2->id, 'bobot' => 100]);

    $this->lanjutkanKurikulumKe($kurikulum, BokState::class);

    $mk1 = Mk::factory()->forAcademicUnit($prodi)->create(['nama' => 'MK E2E Algoritma']);
    $mk2 = Mk::factory()->forAcademicUnit($prodi)->create(['nama' => 'MK E2E Basis Data']);

    $mkUnit1 = MkUnit::factory()->forMk($mk1)->forAcademicUnit($prodi)->create(['kode' => 'E2E101', 'semester_ke' => 3]);
    $mkUnit2 = MkUnit::factory()->forMk($mk2)->forAcademicUnit($prodi)->create(['kode' => 'E2E102', 'semester_ke' => 4]);

    CplMk::query()->create(['cpl_bok_id' => $cplBok1->id, 'mk_id' => $mk1->id, 'bobot' => 100]);
    CplMk::query()->create([
        'cpl_bok_id' => CplBok::query()->where('cpl_id', $cpl2->id)->value('id'),
        'mk_id' => $mk2->id,
        'bobot' => 100,
    ]);

    $this->lanjutkanKurikulumKe($kurikulum, MkState::class);
    expect($kurikulum->fresh()->state->getValue())->toBeIn(['mk', 'setdosenmk']);

    // 4. Admin prodi → kelas_mk + mahasiswa
    $this->actingAs($adminprodiE2E);

    $kelasCollection = collect();
    foreach ([[$mkUnit1, 'A'], [$mkUnit2, 'B']] as [$mkUnit, $kodeKelas]) {
        Livewire::test(CreateKelasMk::class)
            ->fillForm([
                'mk_unit_id' => $mkUnit->id,
                'semester_id' => $semester->id,
                'kode_kelas' => $kodeKelas,
                'dosen_pengampu_id' => $dosenE2E->id,
                'koordinator_mk_id' => $kormaE2E->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $kelas = KelasMk::query()
            ->where('mk_unit_id', $mkUnit->id)
            ->where('kode_kelas', $kodeKelas)
            ->firstOrFail();

        $kelasCollection->push($kelas);
    }

    $mahasiswaRecords = collect();
    foreach (range(1, 5) as $i) {
        $mahasiswaRecords->push(Mahasiswa::factory()->create([
            'academic_unit_id' => $prodi->id,
            'nim' => 'E2E2025'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
            'nama' => "Mahasiswa E2E {$i}",
        ]));
    }

    $kmmPerKelas = [];
    foreach ($kelasCollection as $kelas) {
        foreach ($mahasiswaRecords as $mhs) {
            $kmmPerKelas[$kelas->id][] = KelasMkMahasiswa::query()->create([
                'kelas_mk_id' => $kelas->id,
                'mahasiswa_id' => $mhs->id,
            ]);
        }
    }

    // 5. Koordinator MK → komponen + subcpmk + mapping
    $this->actingAs($kormaE2E);

    $evaluasiUts = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
    $evaluasiUas = Evaluasi::query()->where('kode', 'uas')->firstOrFail();

    $subcpmkPerKelas = [];
    $skpPerKelas = [];

    foreach ($kelasCollection as $index => $kelas) {
        $mk = $index === 0 ? $mk1 : $mk2;
        $cplMk = CplMk::query()->where('mk_id', $mk->id)->firstOrFail();

        Livewire::test(CreateCpmk::class)
            ->fillForm([
                'mk_id' => $mk->id,
                'kode' => 'CPMK-E2E-'.($index + 1),
                'deskripsi' => 'CPMK uji E2E',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $cpmk = Cpmk::query()->where('mk_id', $mk->id)->firstOrFail();
        $mkCpmk = MkCpmk::query()->create([
            'cpl_mk_id' => $cplMk->id,
            'cpmk_id' => $cpmk->id,
            'bobot' => 100,
        ]);

        $subcpmkIds = [];
        foreach (['SUB-E2E-1', 'SUB-E2E-2'] as $kodeSub) {
            Livewire::test(CreateSubcpmk::class)
                ->fillForm([
                    'mk_cpmk_id' => $mkCpmk->id,
                    'semester_id' => $semester->id,
                    'kode' => $kodeSub.'-'.($index + 1),
                    'deskripsi' => 'Sub-CPMK uji E2E',
                    'bobot' => 50,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $subcpmkIds[] = Subcpmk::query()->where('kode', $kodeSub.'-'.($index + 1))->value('id');
        }

        $subcpmkPerKelas[$kelas->id] = Subcpmk::query()->whereIn('id', $subcpmkIds)->get();

        $komponenUts = buatKomponenPenilaian($kelas, $evaluasiUts, 'UTS', 50);
        $komponenUas = buatKomponenPenilaian($kelas, $evaluasiUas, 'UAS', 50);

        $skpIds = [];
        foreach ($subcpmkPerKelas[$kelas->id] as $subcpmk) {
            foreach ([$komponenUts, $komponenUas] as $komponen) {
                $skpIds[] = SubcpmkKomponenPenilaian::query()->create([
                    'subcpmk_id' => $subcpmk->id,
                    'komponen_penilaian_id' => $komponen->id,
                    'bobot' => 100,
                ])->id;
            }
        }

        $skpPerKelas[$kelas->id] = $skpIds;
    }

    // 6. Transisi kurikulum setdosenmk → aktif
    $this->actingAs($timkurE2E);
    $kurikulum->refresh();
    $this->lanjutkanKurikulumKe($kurikulum, SetdosenmkState::class);
    $this->lanjutkanKurikulumKe($kurikulum, AktifState::class);
    $kurikulum->update(['is_active' => true]);

    // 7. Dosen input nilai (matriks per kelas)
    $nilaiInput = 85.0;

    foreach ($kelasCollection as $kelas) {
        $matrix = [];
        foreach ($kmmPerKelas[$kelas->id] as $kmm) {
            foreach ($skpPerKelas[$kelas->id] as $skpId) {
                $matrix[$kmm->id][$skpId] = (string) $nilaiInput;
            }
        }

        NilaiMahasiswa::withoutEvents(function () use ($dosenE2E, $kelas, $matrix): void {
            Livewire::actingAs($dosenE2E)
                ->test(InputNilai::class)
                ->set('kelasMkId', $kelas->id)
                ->set('nilai', $matrix)
                ->call('save')
                ->assertNotified();
        });
    }

    expect(NilaiMahasiswa::query()->count())->toBe(40);

    // 8. Proses antrian in-process (dispatch eksplisit; afterCommit tidak jalan di transaksi test)
    config(['queue.default' => 'database']);

    foreach ($kelasCollection as $kelas) {
        RecalkulasiCplJob::dispatch(
            $kelas->id,
            $prodi->id,
            $semester->id,
        )->onQueue('cpl-calculation');
    }

    while (DB::table('jobs')->exists()) {
        Artisan::call('queue:work', [
            '--once' => true,
            '--queue' => 'cpl-calculation',
        ]);
    }

    // 9. Assert pipeline 5 tahap terisi
    expect(HasilSubcpmk::query()->count())->toBeGreaterThan(0)
        ->and(HasilCpmk::query()->count())->toBeGreaterThan(0)
        ->and(HasilCplMk::query()->count())->toBeGreaterThan(0)
        ->and(HasilCplMkUnit::query()->count())->toBeGreaterThan(0)
        ->and(HasilCplUnit::query()->where('academic_unit_id', $prodi->id)->count())->toBeGreaterThan(0);

    // 10. Kaprodi → dashboard & assert persentase_tercapai
    $this->actingAs($kaprodiE2E);

    $service = app(DashboardCplDataService::class);
    $chart = $service->chartData($prodi->id, $semester->id);

    expect($chart['target'])->toBe(75)
        ->and($chart['rows'])->not->toBeEmpty();

    foreach ($chart['rows'] as $baris) {
        $cpl = Cpl::query()->where('kode', $baris['kode'])->firstOrFail();
        $manualPersentase = hitungPersentaseTercapaiDariHasilCplMk($cpl->id, $prodi->id, $semester->id, 75);

        expect($baris['persentase_tercapai'])->toBe($manualPersentase);
    }

    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Capaian CPL per Unit');

    Livewire::test(CplUnitChartWidget::class, [
        'pageFilters' => [
            'academic_unit_id' => $prodi->id,
            'semester_id' => $semester->id,
        ],
    ])->assertSuccessful();

    $elapsed = microtime(true) - $startedAt;
    expect($elapsed)->toBeLessThan(30.0);

    test()->elapsedSeconds = round($elapsed, 2);
})->group('mvp-e2e');

/**
 * @return KomponenPenilaian
 */
function hitungPersentaseTercapaiDariHasilCplMk(
    string $cplId,
    string $academicUnitId,
    string $semesterId,
    int $threshold,
): float {
    $mkUnitIds = MkUnit::query()
        ->where('academic_unit_id', $academicUnitId)
        ->pluck('id');

    $hasilRows = HasilCplMk::query()
        ->where('cpl_id', $cplId)
        ->where('semester_id', $semesterId)
        ->whereIn('mk_unit_id', $mkUnitIds)
        ->whereNotNull('nilai_berbobot')
        ->with('kelasMkMahasiswa')
        ->get();

    $perMahasiswa = $hasilRows->groupBy(
        fn (HasilCplMk $hasil): string => (string) $hasil->kelasMkMahasiswa?->mahasiswa_id,
    );

    $jumlahMahasiswa = $perMahasiswa->count();

    if ($jumlahMahasiswa === 0) {
        return 0.0;
    }

    $tercapai = $perMahasiswa->filter(
        fn ($baris) => (float) $baris->avg('nilai_berbobot') >= $threshold,
    )->count();

    return round($tercapai / $jumlahMahasiswa * 100, 2);
}

function buatKomponenPenilaian(KelasMk $kelas, Evaluasi $evaluasi, string $nama, float $bobot): KomponenPenilaian
{
    return KomponenPenilaian::query()->create([
        'kelas_mk_id' => $kelas->id,
        'evaluasi_id' => $evaluasi->id,
        'kode' => $nama,
        'nama' => $nama,
        'bobot' => $bobot,
    ]);
}
