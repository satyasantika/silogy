<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource\Pages\ListPenilaianDosens;
use App\Modules\Penilaian\Support\PenilaianSemesterTerpilih;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\Notifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
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
    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $this->dosen = User::where('username', 'dosen')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Aplikasi Komputer Matematika',
    ]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create([
        'kode' => 'KP21514004',
    ]);

    $this->actingAs($this->dosen);
    PenilaianSemesterTerpilih::set($this->semester->id);
});

it('tombol tarik data berada di header tabel bersama filter semester', function () {
    Livewire::test(ListPenilaianDosens::class)
        ->assertSee('Tarik data')
        ->assertTableActionExists('importSintesysDosenPengampu')
        ->assertSeeHtml('silogy-penilaian-dosen');
});

it('tarik dari sintesys mengirim email dosen pengampu dan mengimpor kelas lintas unit', function () {
    $mkFak = Mk::factory()->create([
        'academic_unit_id' => $this->fakultas->id,
        'nama' => 'MK Fakultas Diampu',
    ]);
    $mkUnitFak = MkUnit::factory()->forMk($mkFak)->forAcademicUnit($this->fakultas)->create([
        'kode' => 'FK99001',
    ]);

    Http::fake([
        'sintesys.test/*' => Http::response([
            'tahun_akademik' => $this->semester->kode,
            'kode_prodi' => null,
            'kode_matakuliah' => null,
            'email' => $this->dosen->email,
            'data' => [
                [
                    'kode_mk' => 'KP21514004',
                    'kode_prodi' => $this->prodi->code,
                    'nama_mk' => 'Aplikasi Komputer Matematika',
                    'kelas' => 'A',
                    'dosen_pengampu' => [
                        'nama' => $this->dosen->full_name,
                        'nidn' => $this->dosen->nidn,
                    ],
                    'peserta' => [
                        ['npm' => '259255111003', 'nama' => 'Peserta Uji Dosen'],
                    ],
                ],
                [
                    'kode_mk' => 'FK99001',
                    'kode_prodi' => $this->fakultas->code,
                    'nama_mk' => 'MK Fakultas Diampu',
                    'kelas' => 'B',
                    'dosen_pengampu' => [
                        'nama' => $this->dosen->full_name,
                        'nidn' => $this->dosen->nidn,
                    ],
                    'peserta' => [
                        ['npm' => '259255111004', 'nama' => 'Peserta Fakultas'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $component = Livewire::test(ListPenilaianDosens::class)
        ->mountTableAction('importSintesysDosenPengampu');

    $mountedData = $component->get('mountedTableActions')[0]['data']
        ?? $component->get('mountedActions')[0]['data']
        ?? null;

    expect($mountedData)->not->toBeNull()
        ->and($mountedData['preview_status'])->toBe('ok');

    $component->callMountedTableAction()->assertHasNoActionErrors();

    Http::assertSentCount(1);

    Http::assertSent(function ($request) {
        return $request['tahun_akademik'] === $this->semester->kode
            && $request['email'] === $this->dosen->email;
    });

    $notificationsComponent = new Notifications;
    $notificationsComponent->mount();
    $notification = $notificationsComponent->notifications->first();

    expect($notification)->not->toBeNull()
        ->and($notification->getTitle())->toContain('selesai');

    $kelasProdi = KelasMk::query()
        ->where('mk_unit_id', $this->mkUnit->id)
        ->where('semester_id', $this->semester->id)
        ->where('kode_kelas', 'A')
        ->first();

    $kelasFak = KelasMk::query()
        ->where('mk_unit_id', $mkUnitFak->id)
        ->where('semester_id', $this->semester->id)
        ->where('kode_kelas', 'B')
        ->first();

    expect($kelasProdi)->not->toBeNull()
        ->and($kelasProdi->dosen_pengampu_id)->toBe($this->dosen->id)
        ->and($kelasFak)->not->toBeNull()
        ->and($kelasFak->dosen_pengampu_id)->toBe($this->dosen->id);

    $mhs = Mahasiswa::query()->where('nim', '259255111003')->first();

    expect($mhs)->not->toBeNull()
        ->and(KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasProdi->id)
            ->where('mahasiswa_id', $mhs->id)
            ->exists())->toBeTrue();
});

it('memindahkan mahasiswa dari kelas A ke kelas D pada tarik kedua menghapus pendaftaran kelas lama', function () {
    // Http::fake() tidak mengganti stub lama (append-only) — pakai fakeSequence
    // agar dua panggilan berturut ke endpoint yang sama mendapat respons beda.
    Http::fakeSequence('sintesys.test/*')
        ->push([
            'data' => [[
                'kode_mk' => 'KP21514004',
                'kode_prodi' => $this->prodi->code,
                'kelas' => 'A',
                'dosen_pengampu' => ['nama' => $this->dosen->full_name, 'nidn' => $this->dosen->nidn],
                'peserta' => [
                    ['npm' => '259255111003', 'nama' => 'Peserta Uji Dosen'],
                ],
            ]],
        ], 200)
        ->push([
            'data' => [[
                'kode_mk' => 'KP21514004',
                'kode_prodi' => $this->prodi->code,
                'kelas' => 'A',
                'dosen_pengampu' => ['nama' => $this->dosen->full_name, 'nidn' => $this->dosen->nidn],
                'peserta' => [],
            ], [
                'kode_mk' => 'KP21514004',
                'kode_prodi' => $this->prodi->code,
                'kelas' => 'D',
                'dosen_pengampu' => ['nama' => $this->dosen->full_name, 'nidn' => $this->dosen->nidn],
                'peserta' => [
                    ['npm' => '259255111003', 'nama' => 'Peserta Uji Dosen'],
                ],
            ]],
        ], 200);

    Livewire::test(ListPenilaianDosens::class)
        ->mountTableAction('importSintesysDosenPengampu')
        ->callMountedTableAction()
        ->assertHasNoActionErrors();

    $kelasA = KelasMk::query()
        ->where('mk_unit_id', $this->mkUnit->id)
        ->where('semester_id', $this->semester->id)
        ->where('kode_kelas', 'A')
        ->firstOrFail();

    expect(KelasMkMahasiswa::query()->where('kelas_mk_id', $kelasA->id)->count())->toBe(1);

    Cache::flush();

    Livewire::test(ListPenilaianDosens::class)
        ->mountTableAction('importSintesysDosenPengampu')
        ->callMountedTableAction()
        ->assertHasNoActionErrors();

    $kelasD = KelasMk::query()
        ->where('mk_unit_id', $this->mkUnit->id)
        ->where('semester_id', $this->semester->id)
        ->where('kode_kelas', 'D')
        ->firstOrFail();
    $mahasiswa = Mahasiswa::query()->where('nim', '259255111003')->firstOrFail();

    expect(KelasMkMahasiswa::query()->where('kelas_mk_id', $kelasA->id)->count())->toBe(0)
        ->and(KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasD->id)
            ->where('mahasiswa_id', $mahasiswa->id)
            ->exists())->toBeTrue();
});

it('tidak menghapus peserta kelas lain yang tidak lagi ada di payload dosen ini karena kelas itu direassign ke dosen lain', function () {
    $dosenLain = User::factory()->create(['nidn' => '0099998887']);

    $kelasB = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'B',
        'dosen_pengampu_id' => $dosenLain->id,
    ]);
    $mahasiswaKelasB = Mahasiswa::factory()->create([
        'nim' => '259255111099',
        'academic_unit_id' => $this->prodi->id,
    ]);
    KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $kelasB->id,
        'mahasiswa_id' => $mahasiswaKelasB->id,
    ]);

    Http::fake([
        'sintesys.test/*' => Http::response([
            'data' => [[
                'kode_mk' => 'KP21514004',
                'kode_prodi' => $this->prodi->code,
                'kelas' => 'A',
                'dosen_pengampu' => ['nama' => $this->dosen->full_name, 'nidn' => $this->dosen->nidn],
                'peserta' => [
                    ['npm' => '259255111003', 'nama' => 'Peserta Uji Dosen'],
                ],
            ]],
        ], 200),
    ]);

    Livewire::test(ListPenilaianDosens::class)
        ->mountTableAction('importSintesysDosenPengampu')
        ->callMountedTableAction()
        ->assertHasNoActionErrors();

    expect(KelasMkMahasiswa::query()
        ->where('kelas_mk_id', $kelasB->id)
        ->where('mahasiswa_id', $mahasiswaKelasB->id)
        ->exists())->toBeTrue();
});

it('tetap dapat tarik data dari sintesys walau nidn dosen kosong (memakai email)', function () {
    $this->dosen->update(['nidn' => null]);

    Http::fake([
        'sintesys.test/*' => Http::response([
            'tahun_akademik' => $this->semester->kode,
            'data' => [],
        ], 200),
    ]);

    $component = Livewire::test(ListPenilaianDosens::class)
        ->mountTableAction('importSintesysDosenPengampu');

    $mountedData = $component->get('mountedTableActions')[0]['data']
        ?? $component->get('mountedActions')[0]['data']
        ?? null;

    expect($mountedData)->not->toBeNull()
        ->and($mountedData['preview_status'])->toBe('kosong');

    Http::assertSent(fn ($request) => $request['email'] === $this->dosen->email
        && ! array_key_exists('nidn', $request->data()));
});
