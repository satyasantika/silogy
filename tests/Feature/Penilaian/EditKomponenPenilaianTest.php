<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Support\MkTerpilih;
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\EditKomponenPenilaian;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $this->korma = User::where('username', 'korma')->first();
    $this->semester = Semester::query()->where('status_aktif', true)->first();

    $this->mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create(['kode' => 'IF101']);

    $this->kelas = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'koordinator_mk_id' => $this->korma->id,
    ]);

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Korma Edit',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
});

it('korma dapat menyimpan perubahan komponen penilaian yang valid', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    $komponen = KomponenPenilaian::query()->create([
        'kelas_mk_id' => $this->kelas->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS Awal',
        'bobot' => 100,
    ]);

    Livewire::test(EditKomponenPenilaian::class, ['record' => $komponen->getRouteKey()])
        ->fillForm(['nama' => 'UTS Direvisi'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($komponen->fresh()->nama)->toBe('UTS Direvisi');
});

it('tetap menyimpan perubahan bobot walau total komponen bukan 100', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    $uts = KomponenPenilaian::query()->create([
        'kelas_mk_id' => $this->kelas->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 50,
    ]);
    KomponenPenilaian::query()->create([
        'kelas_mk_id' => $this->kelas->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UAS',
        'nama' => 'UAS',
        'bobot' => 50,
    ]);

    Livewire::test(EditKomponenPenilaian::class, ['record' => $uts->getRouteKey()])
        ->fillForm(['bobot' => 70])
        ->call('save')
        ->assertHasNoFormErrors();

    expect((float) $uts->fresh()->bobot)->toBe(70.0);
});

it('menampilkan total bobot secara realtime saat bobot sedang diisi', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    $uts = KomponenPenilaian::query()->create([
        'kelas_mk_id' => $this->kelas->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 50,
    ]);
    KomponenPenilaian::query()->create([
        'kelas_mk_id' => $this->kelas->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UAS',
        'nama' => 'UAS',
        'bobot' => 50,
    ]);

    Livewire::test(EditKomponenPenilaian::class, ['record' => $uts->getRouteKey()])
        ->fillForm(['bobot' => 70])
        ->assertSee('120.00% dari 100% (lebih 20.00%)', escape: false);

    Livewire::test(EditKomponenPenilaian::class, ['record' => $uts->getRouteKey()])
        ->fillForm(['bobot' => 50])
        ->assertSee('sudah pas 100%', escape: false);
});

it('field kelas mk pada edit tetap terisi sesuai komponen walau mk terpilih berbeda', function () {
    $mkLain = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);

    $uts = KomponenPenilaian::query()->create([
        'kelas_mk_id' => $this->kelas->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 100,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($mkLain->id);

    Livewire::test(EditKomponenPenilaian::class, ['record' => $uts->getRouteKey()])
        ->assertFormSet(['kelas_mk_id' => $this->kelas->id])
        ->fillForm(['nama' => 'UTS Direvisi'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($uts->fresh()->kelas_mk_id)->toBe($this->kelas->id)
        ->and($uts->fresh()->nama)->toBe('UTS Direvisi');
});
