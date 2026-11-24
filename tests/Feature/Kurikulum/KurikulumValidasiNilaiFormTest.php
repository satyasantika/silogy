<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kurikulum\Filament\Resources\KurikulumResource\Pages\EditKurikulum;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Penilaian\Models\ValidasiNilaiKelas;
use App\Modules\Penilaian\Services\ValidasiNilaiService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\SemesterSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

require_once __DIR__.'/../../Support/KelasValidasiFixture.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Form Validasi',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->punyaPeran = fn (User $user): bool => in_array('Validator Nilai', ActiveRole::ownedRoleNames($user), true);

    $this->actingAs(User::query()->where('username', 'timkur')->firstOrFail());
});

it('menyimpan switch dan validator tambahan lalu memberi peran Validator Nilai', function () {
    $upm = User::factory()->create();
    expect(($this->punyaPeran)($upm))->toBeFalse();

    Livewire::test(EditKurikulum::class, ['record' => $this->kurikulum->id])
        ->fillForm(['validasi_nilai_aktif' => true, 'validatorTambahan' => [$upm->id]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->kurikulum->validatorTambahan()->pluck('users.id')->all())->toBe([$upm->id])
        ->and(($this->punyaPeran)($upm))->toBeTrue();

    Livewire::test(EditKurikulum::class, ['record' => $this->kurikulum->id])
        ->fillForm(['validatorTambahan' => []])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->kurikulum->validatorTambahan()->count())->toBe(0)
        ->and(($this->punyaPeran)($upm))->toBeFalse();
});

it('opsi multi-select tidak memuat pimpinan dan tim kurikulum prodi', function () {
    $pimpinan = User::factory()->create(['full_name' => 'Pimpinan Uji Otomatis']);
    $biasa = User::factory()->create(['full_name' => 'Pegawai Uji Biasa']);
    AcademicUnitUser::create(['academic_unit_id' => $this->prodi->id, 'user_id' => $pimpinan->id, 'status_pimpinan' => true]);

    $opsi = Livewire::test(EditKurikulum::class, ['record' => $this->kurikulum->id])
        ->instance()->form->getFlatFields(withHidden: true)['validatorTambahan']->getOptions();

    expect($opsi)->toHaveKey($biasa->id)
        ->and($opsi)->not->toHaveKey($pimpinan->id);
});

it('mematikan switch mencabut peran dan tidak menghapus baris validasi_nilai_kelas', function () {
    $dosen = User::factory()->create();
    $validator = User::factory()->create();
    AcademicUnitUser::create(['academic_unit_id' => $this->prodi->id, 'user_id' => $validator->id, 'status_pimpinan' => true]);
    $this->kurikulum->validatorTambahan()->attach($validator->id);

    $kelas = siapkanKelasValidasi($dosen, $this->prodi)['kelas'];
    $baris = app(ValidasiNilaiService::class)->validasi($kelas, $validator, 'sidik-uji');
    expect(($this->punyaPeran)($validator))->toBeTrue();

    $kurikulumKelas = Kurikulum::query()->findOrFail($kelas->mkUnit->kurikulum_id);

    Livewire::test(EditKurikulum::class, ['record' => $kurikulumKelas->id])
        ->fillForm(['validasi_nilai_aktif' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($kurikulumKelas->fresh()->validasi_nilai_aktif)->toBeFalse();

    // Kurikulum lain di prodi yang sama masih aktif, jadi peran baru tercabut setelah semuanya mati.
    expect(($this->punyaPeran)($validator))->toBeTrue();
    $this->kurikulum->update(['validasi_nilai_aktif' => false]);
    expect(($this->punyaPeran)($validator))->toBeFalse();

    $sesudah = ValidasiNilaiKelas::query()->findOrFail($baris->id);
    expect($sesudah->gugur_at)->toBeNull()
        ->and($sesudah->sidik)->toBe('sidik-uji')
        ->and($sesudah->validator_id)->toBe($validator->id);
});

it('multi-select tersembunyi saat switch mati', function () {
    Livewire::test(EditKurikulum::class, ['record' => $this->kurikulum->id])
        ->fillForm(['validasi_nilai_aktif' => true])
        ->assertFormFieldIsVisible('validatorTambahan')
        ->fillForm(['validasi_nilai_aktif' => false])
        ->assertFormFieldIsHidden('validatorTambahan');
});
