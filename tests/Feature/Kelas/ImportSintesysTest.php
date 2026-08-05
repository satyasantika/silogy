<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\ListKelasMks;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkSintesysImport;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
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
    $this->adminProdi = User::where('username', 'adminprodi')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();

    $this->dosen = User::factory()->create(['nidn' => '0412026601']);
    $this->mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'koordinator_mk_id' => $this->dosen->id]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create(['kode' => 'KP92552012']);
    $this->mahasiswa = Mahasiswa::factory()->create([
        'nim' => '259255111003',
        'academic_unit_id' => $this->prodi->id,
        'angkatan' => null,
    ]);
});

it('menampilkan pratinjau jumlah data yang tersedia lalu menjalankan impor sungguhan setelah dikonfirmasi', function () {
    Http::fake([
        'sintesys.test/*' => Http::response([
            'tahun_akademik' => $this->semester->kode,
            'kode_prodi' => $this->prodi->code,
            'data' => [[
                'kode_mk' => 'KP92552012',
                'kelas' => 'A',
                'dosen_pengampu' => ['nidn' => '0412026601'],
                'peserta' => [['npm' => '259255111003']],
            ]],
        ], 200),
    ]);

    $this->actingAs($this->adminProdi);

    $component = Livewire::test(ListKelasMks::class)
        ->mountAction('importSintesysKelasMk');

    $mountedData = $component->get('mountedActions')[0]['data'];

    expect($mountedData['preview_status'])->toBe('ok');
    expect(json_decode((string) $mountedData['payload_json'], true)['data'])->toHaveCount(1);

    $component->callMountedAction()->assertHasNoActionErrors();

    Http::assertSentCount(1);

    $notificationsComponent = new Notifications;
    $notificationsComponent->mount();
    $notification = $notificationsComponent->notifications->first();

    expect($notification)->not->toBeNull()
        ->and($notification->getTitle())->toContain('selesai')
        ->and($notification->getBody())->toContain('Berhasil: 2 data')
        ->and($notification->getBody())->toContain('Gagal: 0 data');

    $import = KelasMkSintesysImport::query()->where('academic_unit_id', $this->prodi->id)->first();

    expect($import)->not->toBeNull()
        ->and($import->status)->toBe('completed')
        ->and($import->kelas_dibuat)->toBe(1)
        ->and($import->peserta_terdaftar)->toBe(1)
        ->and($import->dibuat_oleh)->toBe($this->adminProdi->id);

    expect(KelasMk::query()->where('kode_kelas', 'A')->exists())->toBeTrue();

    expect($this->mahasiswa->fresh()->angkatan)->toBe('2025');
});

it('melaporkan tanpa membuat apa pun ketika sintesys tidak memiliki data untuk konteks tersebut', function () {
    Http::fake([
        'sintesys.test/*' => Http::response([
            'tahun_akademik' => $this->semester->kode,
            'kode_prodi' => $this->prodi->code,
            'data' => [],
        ], 200),
    ]);

    $this->actingAs($this->adminProdi);

    $component = Livewire::test(ListKelasMks::class)
        ->mountAction('importSintesysKelasMk');

    expect($component->get('mountedActions')[0]['data']['preview_status'])->toBe('kosong');

    $component->callMountedAction()->assertHasNoActionErrors();

    expect(KelasMkSintesysImport::query()->where('academic_unit_id', $this->prodi->id)->exists())->toBeFalse()
        ->and(KelasMk::query()->count())->toBe(0);
});

it('menolak impor sintesys bila filter program studi dimanipulasi ke prodi di luar akses admin', function () {
    $prodiLain = AcademicUnit::factory()->create(['type' => 'study_program', 'code' => 'LAIN01']);

    $this->actingAs($this->adminProdi);

    // Filter academic_unit_id disetel langsung lewat state Livewire (bukan
    // lewat opsi dropdown yang dirender) untuk mensimulasikan input yang
    // dimanipulasi klien; harus tetap ditolak oleh pengecekan scope di aksi.
    Livewire::test(ListKelasMks::class)
        ->set('tableFilters.academic_unit_id.value', $prodiLain->id)
        ->mountAction('importSintesysKelasMk')
        ->callMountedAction();

    expect(KelasMkSintesysImport::query()->where('academic_unit_id', $prodiLain->id)->exists())->toBeFalse();
});

it('menampilkan laporan impor sintesys terakhir milik user pada halaman kelas mk', function () {
    $this->actingAs($this->adminProdi);

    KelasMkSintesysImport::query()->create([
        'semester_id' => $this->semester->id,
        'academic_unit_id' => $this->prodi->id,
        'tahun_akademik' => $this->semester->kode,
        'kode_prodi' => $this->prodi->code,
        'status' => 'completed',
        'total' => 5,
        'processed' => 5,
        'kelas_dibuat' => 2,
        'kelas_diperbarui' => 1,
        'peserta_terdaftar' => 3,
        'peserta_sudah_terdaftar' => 0,
        'errors' => [],
        'dibuat_oleh' => $this->adminProdi->id,
    ]);

    Livewire::test(ListKelasMks::class)
        ->assertSee('Terakhir mengambil data dari Sintesys', escape: false);
});

it('menampilkan pesan gagal pada banner bila percobaan impor sintesys terakhir gagal', function () {
    $this->actingAs($this->adminProdi);

    KelasMkSintesysImport::query()->create([
        'semester_id' => $this->semester->id,
        'academic_unit_id' => $this->prodi->id,
        'tahun_akademik' => $this->semester->kode,
        'kode_prodi' => $this->prodi->code,
        'status' => 'failed',
        'pesan_gagal' => 'Permintaan API Sintesys gagal (HTTP 500).',
        'dibuat_oleh' => $this->adminProdi->id,
    ]);

    Livewire::test(ListKelasMks::class)
        ->assertSee('gagal', escape: false)
        ->assertSee('Permintaan API Sintesys gagal (HTTP 500).', escape: false);
});
