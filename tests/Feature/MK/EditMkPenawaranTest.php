<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Filament\Resources\MkResource\Pages\EditMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
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

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->timkur = User::query()->where('username', 'timkur')->firstOrFail();
    $this->semesterAktif = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->semesterLain = Semester::query()
        ->whereKeyNot($this->semesterAktif->id)
        ->orderByDesc('kode')
        ->firstOrFail();

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Edit Mk Penawaran',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($this->kurikulum->id);

    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Struktur Data',
    ]);

    $this->actingAs($this->timkur);
});

it('halaman edit mk menampilkan panel penawaran dan empty state tanpa mk unit', function () {
    Livewire::test(EditMk::class, ['record' => $this->mk->id])
        ->assertSee('Penawaran mata kuliah')
        ->assertSee('Belum ada penawaran di prodi mana pun untuk mata kuliah ini.')
        ->assertSee('Nama mata kuliah');

    $rekap = Livewire::test(EditMk::class, ['record' => $this->mk->id])
        ->instance()
        ->rekapPenawaranMk();

    expect($rekap['penawaran'])->toBe([])
        ->and($rekap['total_kelas'])->toBe(0)
        ->and($rekap['total_mahasiswa'])->toBe(0);
});

it('rekap penawaran menjumlah kelas dan mahasiswa lintas seluruh semester', function () {
    $mkUnit = MkUnit::factory()->forMk($this->mk)->forKurikulum($this->kurikulum)->create([
        'kode' => 'IF202',
        'semester_ke' => 2,
        'academic_unit_id' => $this->prodi->id,
    ]);

    $kelasA = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semesterAktif->id,
        'kode_kelas' => 'A',
    ]);
    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semesterAktif->id,
        'kode_kelas' => 'B',
    ]);
    $kelasZ = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $this->semesterLain->id,
        'kode_kelas' => 'Z',
    ]);

    $mhs1 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '259255113001']);
    $mhs2 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '259255113002']);
    $mhs3 = Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id, 'nim' => '259255113003']);

    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasA->id, 'mahasiswa_id' => $mhs1->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasA->id, 'mahasiswa_id' => $mhs2->id]);
    KelasMkMahasiswa::query()->create(['kelas_mk_id' => $kelasZ->id, 'mahasiswa_id' => $mhs3->id]);

    $page = Livewire::test(EditMk::class, ['record' => $this->mk->id])
        ->assertSee('Penawaran mata kuliah')
        ->assertSee('IF202')
        ->assertSee('Detail')
        ->assertDontSee('Rincian semester')
        ->assertDontSee('Belum ada penawaran di prodi mana pun untuk mata kuliah ini.');

    $page->call('toggleDetailPenawaran', $mkUnit->id)
        ->assertSet('penawaranDetailId', $mkUnit->id)
        ->assertSee('Rincian semester')
        ->assertSee('Tutup')
        ->assertSee($this->semesterAktif->nama)
        ->assertSee($this->semesterLain->nama);

    $rekap = $page->instance()->rekapPenawaranMk();

    expect($rekap['total_kelas'])->toBe(3)
        ->and($rekap['total_mahasiswa'])->toBe(3)
        ->and($rekap['penawaran'])->toHaveCount(1)
        ->and($rekap['penawaran'][0]['kode'])->toBe('IF202')
        ->and($rekap['penawaran'][0]['jumlah_kelas'])->toBe(3)
        ->and($rekap['penawaran'][0]['jumlah_mahasiswa'])->toBe(3)
        ->and($rekap['penawaran'][0]['per_semester'])->toHaveCount(2);

    $perSemester = collect($rekap['penawaran'][0]['per_semester'])->keyBy('semester_id');

    expect($perSemester[$this->semesterAktif->id]['jumlah_kelas'])->toBe(2)
        ->and($perSemester[$this->semesterAktif->id]['jumlah_mahasiswa'])->toBe(2)
        ->and($perSemester[$this->semesterLain->id]['jumlah_kelas'])->toBe(1)
        ->and($perSemester[$this->semesterLain->id]['jumlah_mahasiswa'])->toBe(1);
});

it('rekap penawaran mencakup adaptasi di semua prodi lintas kurikulum', function () {
    $fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $prodiLain = AcademicUnit::query()->create([
        'parent_id' => $fakultas->id,
        'type' => 'study_program',
        'code' => 'PRODI-LAIN',
        'nama' => 'Prodi Lain Uji',
        'status' => 'aktif',
    ]);

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $prodiLain->id,
        'nama' => 'Kurikulum Prodi Lain',
        'tahun' => 2025,
        'is_active' => true,
    ]);

    MkUnit::factory()->forMk($this->mk)->forKurikulum($this->kurikulum)->create([
        'kode' => 'IF-A',
        'academic_unit_id' => $this->prodi->id,
    ]);
    MkUnit::factory()->forMk($this->mk)->forKurikulum($kurikulumLain)->create([
        'kode' => 'IF-B',
        'academic_unit_id' => $prodiLain->id,
    ]);

    $page = Livewire::test(EditMk::class, ['record' => $this->mk->id])
        ->assertSee('IF-A')
        ->assertSee('IF-B')
        ->assertSee('Kurikulum Prodi Lain');

    $rekap = $page->instance()->rekapPenawaranMk();

    expect($rekap['penawaran'])->toHaveCount(2)
        ->and(collect($rekap['penawaran'])->pluck('kode')->sort()->values()->all())->toBe(['IF-A', 'IF-B']);
});
