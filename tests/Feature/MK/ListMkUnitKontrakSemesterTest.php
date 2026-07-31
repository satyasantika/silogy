<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\ListMkUnits;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\SemesterKontrakPenawaran;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    config(['services.sintesys.endpoint' => 'https://sintesys.test/api/akademik/detail_nilai']);
    config(['services.sintesys.token' => 'token-uji']);

    session()->forget(SemesterKontrakPenawaran::SESSION_KEY);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->semesterAktif = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->semesterLain = Semester::query()
        ->whereKeyNot($this->semesterAktif->id)
        ->orderByDesc('kode')
        ->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum List MkUnit Kontrak',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);
    SemesterKontrakPenawaran::set($this->semesterAktif->id);

    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'nama' => 'Matematika Diskrit',
    ]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forKurikulum($this->kurikulum)->create([
        'kode' => 'KP21514099',
    ]);

    $this->actingAs($this->timkur);
});

it('list penawaran mk menampilkan filter semester dan tanpa aksi ubah', function () {
    Livewire::test(ListMkUnits::class)
        ->loadTable()
        ->assertSee('Mahasiswa kontrak')
        ->assertTableActionExists('tarikKontrak')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionVisible('tarikKontrak', $this->mkUnit);
});

it('kolom mahasiswa kontrak mengikuti semester penawaran terpilih', function () {
    $kelasAktif = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semesterAktif->id,
        'kode_kelas' => 'A',
    ]);
    $kelasLain = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semesterLain->id,
        'kode_kelas' => 'B',
    ]);

    $mhs1 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '259255111101']);
    $mhs2 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '259255111102']);
    $mhs3 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '259255111103']);

    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasAktif->id, 'mahasiswa_id' => $mhs1->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasAktif->id, 'mahasiswa_id' => $mhs2->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasLain->id, 'mahasiswa_id' => $mhs3->id]);

    SemesterKontrakPenawaran::set($this->semesterAktif->id);

    $halaman = Livewire::test(ListMkUnits::class)->loadTable();

    expect((int) $halaman->instance()->getFilteredTableQuery()->first()->jumlah_mahasiswa_kontrak)->toBe(2);

    SemesterKontrakPenawaran::set($this->semesterLain->id);

    $halamanLain = Livewire::test(ListMkUnits::class)
        ->set('tableFilters.semester_kontrak_penawaran.value', $this->semesterLain->id)
        ->loadTable();

    expect((int) $halamanLain->instance()->getFilteredTableQuery()->first()->jumlah_mahasiswa_kontrak)->toBe(1)
        ->and(SemesterKontrakPenawaran::currentId())->toBe($this->semesterLain->id);
});

it('aksi tarik data di list memakai tahun akademik semester terpilih', function () {
    User::factory()->create(['nidn' => '0027118602', 'full_name' => 'SATYA SANTIKA']);

    Http::fake([
        'sintesys.test/*' => Http::response([
            'tahun_akademik' => (int) $this->semesterAktif->kode,
            'kode_prodi' => $this->prodi->code,
            'kode_matakuliah' => 'KP21514099',
            'data' => [[
                'kode_mk' => 'KP21514099',
                'kelas' => 'A',
                'dosen_pengampu' => [
                    'nama' => 'SATYA SANTIKA',
                    'nidn' => '0027118602',
                ],
                'peserta' => [
                    ['npm' => '259255111201', 'nama' => 'Peserta List A'],
                ],
            ]],
        ], 200),
    ]);

    SemesterKontrakPenawaran::set($this->semesterAktif->id);

    Livewire::test(ListMkUnits::class)
        ->loadTable()
        ->callTableAction('tarikKontrak', $this->mkUnit)
        ->assertHasNoErrors();

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->url() === 'https://sintesys.test/api/akademik/detail_nilai'
            && (int) $data['tahun_akademik'] === (int) $this->semesterAktif->kode
            && (string) $data['kode_prodi'] === (string) $this->prodi->code
            && $data['kode_matakuliah'] === 'KP21514099';
    });

    expect(
        KelasMk::query()
            ->where('mk_unit_id', $this->mkUnit->id)
            ->where('semester_id', $this->semesterAktif->id)
            ->where('kode_kelas', 'A')
            ->exists()
    )->toBeTrue()
        ->and(
            KelasMkMahasiswa::query()
                ->whereHas('kelasMk', fn ($q) => $q
                    ->where('mk_unit_id', $this->mkUnit->id)
                    ->where('semester_id', $this->semesterAktif->id))
                ->count()
        )->toBe(1);
});
