<?php

use App\Models\User;
use App\Modules\Auth\Support\ActiveRole;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource\Pages\ListPenilaianDosens;
use App\Modules\Penilaian\Filament\Widgets\RekapMkDosenWidget;
use App\Modules\Penilaian\Support\PenilaianSemesterTerpilih;
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
    $this->dosen = User::where('username', 'dosen')->firstOrFail();
    $this->korma = User::where('username', 'korma')->firstOrFail();
    $this->semesterAktif = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->semesterLain = Semester::query()->where('id', '!=', $this->semesterAktif->id)->firstOrFail();
});

function buatKelasDosenUntukWidget(
    User $dosen,
    AcademicUnit $prodi,
    string $namaMk,
    string $kodeMkUnit,
    string $kodeKelas,
    string $semesterId,
): KelasMk {
    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id, 'nama' => $namaMk]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($prodi)->create(['kode' => $kodeMkUnit]);

    return KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semesterId,
        'kode_kelas' => $kodeKelas,
        'dosen_pengampu_id' => $dosen->id,
    ]);
}

it('menampilkan rekap jumlah mata kuliah per semester untuk dosen pengampu', function () {
    buatKelasDosenUntukWidget($this->dosen, $this->prodi, 'Struktur Data', 'IF201', 'A', $this->semesterAktif->id);
    buatKelasDosenUntukWidget($this->dosen, $this->prodi, 'Basis Data', 'IF202', 'A', $this->semesterAktif->id);
    buatKelasDosenUntukWidget($this->dosen, $this->prodi, 'Kalkulus', 'IF203', 'A', $this->semesterLain->id);

    $this->actingAs($this->dosen);

    Livewire::test(RekapMkDosenWidget::class)
        ->assertSee($this->semesterAktif->nama, escape: false)
        ->assertSee('2 mata kuliah diampu', escape: false)
        ->assertSee($this->semesterLain->nama, escape: false)
        ->assertSee('1 mata kuliah diampu', escape: false);
});

it('menampilkan keterangan belum ada penugasan bila dosen belum mengampu kelas mana pun', function () {
    $this->actingAs($this->dosen);

    Livewire::test(RekapMkDosenWidget::class)
        ->assertSee('belum ditugaskan sebagai dosen pengampu', escape: false);
});

it('widget hanya tampil untuk role dosen pengampu', function () {
    $this->actingAs($this->dosen);
    expect(RekapMkDosenWidget::canView())->toBeTrue();

    $this->actingAs($this->korma);
    expect(RekapMkDosenWidget::canView())->toBeFalse();
});

it('widget mengikuti role aktif pada user multi-role', function () {
    $dosenTimkur = User::where('username', 'dosentimkur')->firstOrFail();
    $this->actingAs($dosenTimkur);

    ActiveRole::set('Dosen Pengampu');
    expect(RekapMkDosenWidget::canView())->toBeTrue();

    ActiveRole::set('Tim Kurikulum');
    expect(RekapMkDosenWidget::canView())->toBeFalse();
});

it('mengunjungi penilaian dengan query semester_id langsung menerapkan filter semester tersebut', function () {
    buatKelasDosenUntukWidget($this->dosen, $this->prodi, 'Struktur Data', 'IF201', 'A', $this->semesterAktif->id);
    buatKelasDosenUntukWidget($this->dosen, $this->prodi, 'Kalkulus', 'IF203', 'A', $this->semesterLain->id);

    $this->actingAs($this->dosen);
    PenilaianSemesterTerpilih::set($this->semesterAktif->id);

    Livewire::withQueryParams(['semester_id' => $this->semesterLain->id])
        ->test(ListPenilaianDosens::class)
        ->assertSee('Kalkulus', escape: false)
        ->assertDontSee('Struktur Data', escape: false);

    expect(PenilaianSemesterTerpilih::currentId())->toBe($this->semesterLain->id);
});
