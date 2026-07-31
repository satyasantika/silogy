<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource\Pages\ListPenilaianDosens;
use App\Modules\Penilaian\Filament\Widgets\DosenMkDiampuWidget;
use App\Modules\Penilaian\Filament\Widgets\DosenPengampuKpiWidget;
use App\Modules\Penilaian\Services\DashboardDosenPengampuService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * Buat kelas diampu dosen pada penawaran MK di unit tertentu (prodi/fak/univ).
 */
function buatKelasDiampuPadaUnit(User $dosen, AcademicUnit $unit, string $namaMk, string $semesterId, string $kodeKelas): Mk
{
    $mk = Mk::factory()->create([
        'academic_unit_id' => $unit->id,
        'nama' => $namaMk,
    ]);

    $mkUnit = MkUnit::factory()->forMk($mk)->create([
        'academic_unit_id' => $unit->id,
    ]);

    KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id,
        'semester_id' => $semesterId,
        'kode_kelas' => $kodeKelas,
        'dosen_pengampu_id' => $dosen->id,
    ]);

    return $mk;
}

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->firstOrFail();
    $this->univ = AcademicUnit::query()->where('type', 'university')->firstOrFail();
    $this->dosen = User::where('username', 'dosen')->firstOrFail();
    $this->semesterAktif = Semester::query()->where('status_aktif', true)->firstOrFail();
});

it('mendeteksi mk diampu pada penawaran fakultas dan universitas selain prodi', function () {
    $this->actingAs($this->dosen);

    $mkProdi = buatKelasDiampuPadaUnit(
        $this->dosen,
        $this->prodi,
        'MK Prodi Dashboard',
        $this->semesterAktif->id,
        'P1',
    );
    $mkFak = buatKelasDiampuPadaUnit(
        $this->dosen,
        $this->fakultas,
        'MK Fakultas Dashboard',
        $this->semesterAktif->id,
        'F1',
    );
    $mkUniv = buatKelasDiampuPadaUnit(
        $this->dosen,
        $this->univ,
        'MK Universitas Dashboard',
        $this->semesterAktif->id,
        'U1',
    );

    $service = app(DashboardDosenPengampuService::class);

    expect($service->jumlahMkDiampu($this->dosen))->toBe(3)
        ->and($service->jumlahKelasDiampu($this->dosen))->toBe(3);

    Livewire::test(ListPenilaianDosens::class)
        ->assertCanSeeTableRecords([$this->prodi, $this->fakultas, $this->univ])
        ->assertSee('MK Prodi Dashboard', escape: false)
        ->assertSee('MK Fakultas Dashboard', escape: false)
        ->assertSee('MK Universitas Dashboard', escape: false);
});

it('kpi dashboard dosen menampilkan mk dan kelas diampu tanpa card mk sedang dikerjakan', function () {
    $this->actingAs($this->dosen);

    buatKelasDiampuPadaUnit(
        $this->dosen,
        $this->fakultas,
        'MK Fakultas KPI',
        $this->semesterAktif->id,
        'A',
    );
    buatKelasDiampuPadaUnit(
        $this->dosen,
        $this->univ,
        'MK Universitas KPI',
        $this->semesterAktif->id,
        'B',
    );

    expect(DosenMkDiampuWidget::canView())->toBeFalse()
        ->and(DosenPengampuKpiWidget::canView())->toBeTrue();

    Livewire::test(DosenPengampuKpiWidget::class)
        ->assertSee('MK Diampu', escape: false)
        ->assertSee('Kelas Diampu', escape: false)
        ->assertSee('2', escape: false)
        ->assertDontSee('MK Sedang Dikerjakan', escape: false)
        ->assertDontSee('Pilih lewat widget di bawah', escape: false);
});

it('menu penilaian dosen diganti menjadi pengampu mk', function () {
    $this->actingAs($this->dosen);

    expect(PenilaianDosenResource::getNavigationLabel())->toBe('Pengampu MK')
        ->and(PenilaianDosenResource::getNavigationGroup())->toBe('Pengampu MK');
});
