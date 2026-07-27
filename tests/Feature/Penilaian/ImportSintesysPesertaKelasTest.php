<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Resources\PesertaKelasResource\Pages\ListPesertaKelas;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Filament\Notifications\Livewire\Notifications;
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
    $this->korma = User::query()->where('username', 'korma')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Korma Peserta',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
        'nama' => 'Aplikasi Komputer Matematika',
    ]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create(['kode' => 'KP21514004']);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);
});

it('impor massal nim nama kelas membuat kelas dan mendaftarkan mahasiswa baru pada mk terpilih', function () {
    Livewire::test(ListPesertaKelas::class)
        ->callAction('bulkImport', data: [
            'import_mk_id' => $this->mk->id,
            'import_semester_id' => $this->semester->id,
            'rows' => "227000001|Mahasiswa Uji Satu|A\n227000002|Mahasiswa Uji Dua|A",
        ])
        ->assertHasNoActionErrors();

    $kelas = KelasMk::query()
        ->where('mk_unit_id', $this->mkUnit->id)
        ->where('semester_id', $this->semester->id)
        ->where('kode_kelas', 'A')
        ->first();

    expect($kelas)->not->toBeNull();

    $mahasiswa1 = Mahasiswa::query()->where('nim', '227000001')->first();
    $mahasiswa2 = Mahasiswa::query()->where('nim', '227000002')->first();

    expect($mahasiswa1)->not->toBeNull()
        ->and($mahasiswa1->nama)->toBe('Mahasiswa Uji Satu')
        ->and($mahasiswa1->academic_unit_id)->toBe($this->prodi->id)
        ->and($mahasiswa2)->not->toBeNull();

    expect(KelasMkMahasiswa::query()->where('kelas_mk_id', $kelas->id)->where('mahasiswa_id', $mahasiswa1->id)->exists())->toBeTrue()
        ->and(KelasMkMahasiswa::query()->where('kelas_mk_id', $kelas->id)->where('mahasiswa_id', $mahasiswa2->id)->exists())->toBeTrue();
});

it('tarik dari sintesys mengirim kode_matakuliah pada request dan mengimpor peserta hanya untuk mk terpilih', function () {
    $dosen = User::factory()->create(['nidn' => '0027118602']);

    Http::fake([
        'sintesys.test/*' => Http::response([
            'tahun_akademik' => $this->semester->kode,
            'kode_prodi' => $this->prodi->code,
            'kode_matakuliah' => 'KP21514004',
            'nidn' => null,
            'nuptk' => null,
            'email' => null,
            'data' => [[
                'kode_mk' => 'KP21514004',
                'nama_mk' => 'Aplikasi Komputer Matematika',
                'kelas' => 'A',
                'sks' => 3,
                'dosen_pengampu' => [
                    'nama' => 'SATYA SANTIKA',
                    'nidn' => '0027118602',
                    'nuptk' => '9459764665130203',
                ],
                'peserta' => [
                    ['nim' => '259255111003', 'nama' => 'Peserta Uji Sintesys'],
                ],
            ]],
        ], 200),
    ]);

    $component = Livewire::test(ListPesertaKelas::class)
        ->mountAction('importSintesysPesertaKelas');

    $mountedData = $component->get('mountedActions')[0]['data'];

    expect($mountedData['preview_status'])->toBe('ok');

    $component->callMountedAction()->assertHasNoActionErrors();

    Http::assertSentCount(1);

    Http::assertSent(function ($request) {
        return $request['tahun_akademik'] === $this->semester->kode
            && $request['kode_prodi'] === (string) $this->prodi->code
            && $request['kode_matakuliah'] === 'KP21514004';
    });

    $notificationsComponent = new Notifications;
    $notificationsComponent->mount();
    $notification = $notificationsComponent->notifications->first();

    expect($notification)->not->toBeNull()
        ->and($notification->getTitle())->toContain('selesai');

    $kelas = KelasMk::query()
        ->where('mk_unit_id', $this->mkUnit->id)
        ->where('semester_id', $this->semester->id)
        ->where('kode_kelas', 'A')
        ->first();

    expect($kelas)->not->toBeNull()
        ->and($kelas->dosen_pengampu_id)->toBe($dosen->id);

    $mahasiswa = Mahasiswa::query()->where('nim', '259255111003')->first();

    expect($mahasiswa)->not->toBeNull()
        ->and($mahasiswa->nama)->toBe('Peserta Uji Sintesys')
        ->and($mahasiswa->academic_unit_id)->toBe($this->prodi->id);

    expect(KelasMkMahasiswa::query()->where('kelas_mk_id', $kelas->id)->where('mahasiswa_id', $mahasiswa->id)->exists())->toBeTrue();
});

it('mengabaikan baris data sintesys yang kode_mk-nya bukan mk terpilih', function () {
    $mkLain = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    MkUnit::factory()->forMk($mkLain)->forAcademicUnit($this->prodi)->create(['kode' => 'KP21514099']);

    Http::fake([
        'sintesys.test/*' => Http::response([
            'tahun_akademik' => $this->semester->kode,
            'kode_prodi' => $this->prodi->code,
            'kode_matakuliah' => 'KP21514004',
            'data' => [[
                'kode_mk' => 'KP21514099',
                'kelas' => 'A',
                'peserta' => [
                    ['nim' => '259255111099', 'nama' => 'Peserta MK Lain'],
                ],
            ]],
        ], 200),
    ]);

    Livewire::test(ListPesertaKelas::class)
        ->mountAction('importSintesysPesertaKelas')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect(Mahasiswa::query()->where('nim', '259255111099')->exists())->toBeFalse()
        ->and(KelasMk::query()->where('kode_kelas', 'A')->exists())->toBeFalse();
});
