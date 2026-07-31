<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\EditMkUnit;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
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

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Edit MkUnit',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'nama' => 'Aplikasi Komputer Matematika',
    ]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forKurikulum($this->kurikulum)->create([
        'kode' => 'KP21514004',
    ]);

    $this->actingAs($this->timkur);
});

it('halaman edit penawaran mk menampilkan banner kurikulum dan kontrol semester', function () {
    Livewire::test(EditMkUnit::class, ['record' => $this->mkUnit->id])
        ->assertSee('Kurikulum yang dikerjakan')
        ->assertSee('Penawaran MK ini dikelola pada kurikulum prodi yang dikerjakan')
        ->assertSee('Kontrak kelas')
        ->assertDontSee('Kontrak kelas (Sintesys)')
        ->assertSet('semesterKontrakId', $this->semester->id)
        // Semester aktif seeder = 20251 → tombol Sintesys.
        ->assertSee('Tarik data Sintesys');
});

it('tombol tarik menyesuaikan sumber: Sintesys sejak 20251, Simak untuk sebelumnya', function () {
    $semesterSimak = Semester::query()->where('kode', '20242')->firstOrFail();
    $semesterSintesys = Semester::query()->where('kode', '20251')->firstOrFail();

    $page = Livewire::test(EditMkUnit::class, ['record' => $this->mkUnit->id]);

    expect($page->instance()->sumberTarikUntukSemester($semesterSimak))->toBe('simak')
        ->and($page->instance()->sumberTarikUntukSemester($semesterSintesys))->toBe('sintesys');

    $page->set('semesterKontrakId', $semesterSimak->id)
        ->assertSee('Tarik data Simak')
        ->assertDontSee('Tarik data Sintesys');

    $page->set('semesterKontrakId', $semesterSintesys->id)
        ->assertSee('Tarik data Sintesys');
});

it('tarik data sintesys mengirim body tahun_akademik kode_prodi kode_matakuliah untuk semester terpilih', function () {
    $dosen = User::factory()->create(['nidn' => '0027118602', 'full_name' => 'SATYA SANTIKA']);

    Http::fake([
        'sintesys.test/*' => Http::response([
            'tahun_akademik' => (int) $this->semester->kode,
            'kode_prodi' => $this->prodi->code,
            'kode_matakuliah' => 'KP21514004',
            'data' => [[
                'kode_mk' => 'KP21514004',
                'kelas' => 'A',
                'dosen_pengampu' => [
                    'nama' => 'SATYA SANTIKA',
                    'nidn' => '0027118602',
                ],
                'peserta' => [
                    ['npm' => '259255111003', 'nama' => 'Peserta Kelas A'],
                    ['npm' => '259255111004', 'nama' => 'Peserta Kelas A Dua'],
                ],
            ], [
                'kode_mk' => 'KP21514004',
                'kelas' => 'B',
                'dosen_pengampu' => [
                    'nama' => 'SATYA SANTIKA',
                    'nidn' => '0027118602',
                ],
                'peserta' => [
                    ['npm' => '259255111005', 'nama' => 'Peserta Kelas B'],
                ],
            ]],
        ], 200),
    ]);

    $page = Livewire::test(EditMkUnit::class, ['record' => $this->mkUnit->id])
        ->set('semesterKontrakId', $this->semester->id)
        ->mountAction('tarikKontrakKelas')
        ->assertMountedActionModalSee('siap diimpor')
        ->assertMountedActionModalSee('Impor sekarang')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    Http::assertSent(function ($request): bool {
        $data = $request->data();

        return $request->url() === 'https://sintesys.test/api/akademik/detail_nilai'
            && (int) $data['tahun_akademik'] === (int) $this->semester->kode
            && (string) $data['kode_prodi'] === (string) $this->prodi->code
            && $data['kode_matakuliah'] === 'KP21514004'
            && ! array_key_exists('nidn', $data);
    });

    $kelasA = KelasMk::query()
        ->where('mk_unit_id', $this->mkUnit->id)
        ->where('semester_id', $this->semester->id)
        ->where('kode_kelas', 'A')
        ->first();

    $kelasB = KelasMk::query()
        ->where('mk_unit_id', $this->mkUnit->id)
        ->where('semester_id', $this->semester->id)
        ->where('kode_kelas', 'B')
        ->first();

    expect($kelasA)->not->toBeNull()
        ->and($kelasA->dosen_pengampu_id)->toBe($dosen->id)
        ->and($kelasB)->not->toBeNull()
        ->and(KelasMkMahasiswa::query()->where('kelas_mk_id', $kelasA->id)->count())->toBe(2)
        ->and(KelasMkMahasiswa::query()->where('kelas_mk_id', $kelasB->id)->count())->toBe(1);

    $rekap = $page->instance()->rekapKelasUntukSemester();

    expect($rekap)->toHaveCount(2)
        ->and($rekap[0]['kode_kelas'])->toBe('A')
        ->and($rekap[0]['dosen'])->toBe('SATYA SANTIKA')
        ->and($rekap[0]['jumlah_mahasiswa'])->toBe(2)
        ->and($rekap[1]['kode_kelas'])->toBe('B')
        ->and($rekap[1]['jumlah_mahasiswa'])->toBe(1);

    $page->call('pilihDetailKelas', $kelasA->id)
        ->assertSet('kelasDetailId', $kelasA->id)
        ->assertSee('Daftar mahasiswa — Kelas A')
        ->assertSee('259255111003')
        ->assertSee('Peserta Kelas A');

    $page->call('pilihDetailKelas', $kelasB->id)
        ->assertSet('kelasDetailId', $kelasB->id)
        ->assertSee('Daftar mahasiswa — Kelas B')
        ->assertSee('259255111005')
        ->assertDontSee('259255111003');
});

it('tarik kontrak semester simak memanggil endpoint simak bukan sintesys', function () {
    config(['services.simak.endpoint' => 'https://simak.test/api/kontrak']);
    config(['services.simak.token' => 'token-simak']);

    $semesterSimak = Semester::query()->where('kode', '20242')->firstOrFail();

    Http::fake([
        'simak.test/*' => Http::response([
            'tahun_akademik' => 20242,
            'kode_prodi' => $this->prodi->code,
            'kode_matakuliah' => 'KP21514004',
            'data' => [[
                'kode_mk' => 'KP21514004',
                'kelas' => 'C',
                'dosen_pengampu' => ['nidn' => '', 'nama' => ''],
                'peserta' => [
                    ['npm' => '249255111001', 'nama' => 'Peserta Simak'],
                ],
            ]],
        ], 200),
        'sintesys.test/*' => Http::response(['data' => []], 200),
    ]);

    Livewire::test(EditMkUnit::class, ['record' => $this->mkUnit->id])
        ->set('semesterKontrakId', $semesterSimak->id)
        ->mountAction('tarikKontrakKelas')
        ->assertMountedActionModalSee('siap diimpor')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'simak.test'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'sintesys.test'));

    expect(
        KelasMk::query()
            ->where('mk_unit_id', $this->mkUnit->id)
            ->where('semester_id', $semesterSimak->id)
            ->where('kode_kelas', 'C')
            ->exists()
    )->toBeTrue();
});

it('rekap kelas hanya menampilkan kelas pada semester yang dipilih', function () {
    $semesterLain = Semester::query()
        ->whereKeyNot($this->semester->id)
        ->orderByDesc('kode')
        ->firstOrFail();

    $kelasSemesterIni = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
    ]);
    $mahasiswa = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '242151199001']);
    KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasSemesterIni->id,
        'mahasiswa_id' => $mahasiswa->id,
    ]);

    KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $semesterLain->id,
        'kode_kelas' => 'Z',
    ]);

    $page = Livewire::test(EditMkUnit::class, ['record' => $this->mkUnit->id])
        ->set('semesterKontrakId', $this->semester->id);

    $rekap = $page->instance()->rekapKelasUntukSemester();

    expect($rekap)->toHaveCount(1)
        ->and($rekap[0]['kode_kelas'])->toBe('A')
        ->and($rekap[0]['jumlah_mahasiswa'])->toBe(1);

    $page->set('semesterKontrakId', $semesterLain->id);
    $rekapLain = $page->instance()->rekapKelasUntukSemester();

    expect($rekapLain)->toHaveCount(1)
        ->and($rekapLain[0]['kode_kelas'])->toBe('Z')
        ->and($page->get('kelasDetailId'))->toBeNull();
});
