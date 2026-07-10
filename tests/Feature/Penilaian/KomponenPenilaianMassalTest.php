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
use App\Modules\Penilaian\Filament\Resources\KomponenPenilaianResource\Pages\CreateKomponenPenilaian;
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
    $this->superadmin = User::where('username', 'superadmin')->first();
    $this->semester = Semester::query()->where('status_aktif', true)->first();

    $this->mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);
    $this->mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create(['kode' => 'IF101']);

    $this->kelasA = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'A',
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $this->kelasB = KelasMk::query()->create([
        'mk_unit_id' => $this->mkUnit->id,
        'semester_id' => $this->semester->id,
        'kode_kelas' => 'B',
        'koordinator_mk_id' => $this->korma->id,
    ]);

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Korma Massal',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();
});

it('membuat asesmen baru untuk semua kelas pada mk dan semester terpilih', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(CreateKomponenPenilaian::class)
        ->fillForm([
            'evaluasi_id' => $this->evaluasi->id,
            'kode' => 'UTS',
            'nama' => 'UTS Teori',
            'bobot' => 100,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $komponens = KomponenPenilaian::query()->where('kode', 'UTS')->get();

    expect($komponens)->toHaveCount(2)
        ->and($komponens->pluck('kelas_mk_id')->sort()->values()->all())
        ->toBe(collect([$this->kelasA->id, $this->kelasB->id])->sort()->values()->all())
        ->and($komponens->pluck('nama')->unique()->all())->toBe(['UTS Teori']);
});

it('label kelas mk berubah menjadi mata kuliah saat mode massal aktif', function () {
    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(CreateKomponenPenilaian::class)
        ->assertSee('Mata Kuliah', escape: false)
        ->assertDontSee('Kelas MK', escape: false);
});

it('mengedit asesmen menerapkan perubahan ke kelas lain berkode sama', function () {
    $utsA = KomponenPenilaian::query()->create([
        'kelas_mk_id' => $this->kelasA->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 100,
    ]);
    $utsB = KomponenPenilaian::query()->create([
        'kelas_mk_id' => $this->kelasB->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 100,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(EditKomponenPenilaian::class, ['record' => $utsA->getRouteKey()])
        ->assertSee('Mata Kuliah', escape: false)
        ->fillForm(['nama' => 'UTS Direvisi', 'kode' => 'UTS-1'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($utsA->fresh())
        ->nama->toBe('UTS Direvisi')
        ->kode->toBe('UTS-1')
        ->and($utsB->fresh())
        ->nama->toBe('UTS Direvisi')
        ->kode->toBe('UTS-1');
});

it('total bobot dihitung terhadap mata kuliah, bukan dijumlah per kelas', function () {
    $utsA = KomponenPenilaian::query()->create([
        'kelas_mk_id' => $this->kelasA->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 69,
    ]);
    KomponenPenilaian::query()->create([
        'kelas_mk_id' => $this->kelasB->id,
        'evaluasi_id' => $this->evaluasi->id,
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 69,
    ]);

    $this->actingAs($this->korma);
    MkTerpilih::set($this->mk->id);

    Livewire::test(EditKomponenPenilaian::class, ['record' => $utsA->getRouteKey()])
        ->assertSee('Total bobot komponen pada mata kuliah dan semester ini: 69.00% dari 100% (kurang 31.00%).', escape: false)
        ->assertDontSee('pada kelas ini', escape: false);
});

it('mode legacy tetap berlaku saat mode massal tidak tersedia', function () {
    $this->actingAs($this->superadmin);

    Livewire::test(CreateKomponenPenilaian::class)
        ->fillForm([
            'kelas_mk_id' => $this->kelasA->id,
            'evaluasi_id' => $this->evaluasi->id,
            'kode' => 'UTS',
            'nama' => 'UTS Teori',
            'bobot' => 100,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $komponens = KomponenPenilaian::query()->where('kode', 'UTS')->get();

    expect($komponens)->toHaveCount(1)
        ->and($komponens->first()->kelas_mk_id)->toBe($this->kelasA->id);
});
