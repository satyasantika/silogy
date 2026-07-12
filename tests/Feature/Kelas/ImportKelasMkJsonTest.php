<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\ListKelasMks;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('aksi impor json terpasang di header dan berhasil memproses file upload', function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Storage::fake('local');

    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $adminProdi = User::where('username', 'adminprodi')->firstOrFail();
    $dosen = User::factory()->create(['nidn' => '0412026601']);

    $mk = Mk::factory()->forAcademicUnit($prodi)->create(['koordinator_mk_id' => $adminProdi->id]);
    MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create(['kode' => 'KP92552012']);

    $mahasiswa = Mahasiswa::factory()->create(['nim' => '259255111003', 'academic_unit_id' => $prodi->id]);

    $payload = [
        'tahun_akademik' => 20252,
        'kode_prodi' => $prodi->code,
        'data' => [[
            'kode_mk' => 'KP92552012',
            'kelas' => 'A',
            'dosen_pengampu' => ['nidn' => '0412026601'],
            'peserta' => [['npm' => 259255111003]],
        ]],
    ];

    $this->actingAs($adminProdi);

    Livewire::test(ListKelasMks::class)
        ->assertActionExists('importJsonKelasMk')
        ->callAction('importJsonKelasMk', data: [
            'sumber' => 'file',
            'json_file' => UploadedFile::fake()->createWithContent('kelas.json', json_encode($payload)),
        ])
        ->assertHasNoActionErrors();

    $kelas = KelasMk::query()->where('kode_kelas', 'A')->first();

    expect($kelas)->not->toBeNull()
        ->and($kelas->dosen_pengampu_id)->toBe($dosen->id)
        ->and($kelas->koordinator_mk_id)->toBe($adminProdi->id);

    expect(KelasMkMahasiswa::query()
        ->where('kelas_mk_id', $kelas->id)
        ->where('mahasiswa_id', $mahasiswa->id)
        ->exists())->toBeTrue();
});
