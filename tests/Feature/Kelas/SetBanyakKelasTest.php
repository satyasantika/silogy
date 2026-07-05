<?php

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\ListKelasMks;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Services\SetBanyakKelasMkService;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
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
    $this->adminProdi = User::where('username', 'adminprodi')->firstOrFail();
    $this->semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Set Kelas',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    KurikulumTerpilih::set($this->kurikulum->id);

    $this->mkA = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Statistika']);
    $this->mkB = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Basis Data']);
    $this->mkUnitA = MkUnit::factory()->forMk($this->mkA)->forAcademicUnit($this->prodi)->create([
        'kode' => 'UI201',
        'semester_ke' => 3,
    ]);
    $this->mkUnitB = MkUnit::factory()->forMk($this->mkB)->forAcademicUnit($this->prodi)->create([
        'kode' => 'UI202',
        'semester_ke' => 3,
    ]);

    $this->actingAs($this->adminProdi);
});

it('filter kurikulum aktif otomatis terpilih saat halaman dimuat', function () {
    Livewire::test(ListKelasMks::class)
        ->loadTable()
        ->assertSet('tableFilters.kurikulum_terpilih.value', $this->kurikulum->id)
        ->assertSee('Kurikulum Set Kelas', escape: false);
});

it('koordinator mk melihat opsi kurikulum prodi pada filter kelas mk', function () {
    $korma = User::where('username', 'korma')->firstOrFail();
    $this->actingAs($korma);

    $unitIds = \App\Modules\Kelas\Filament\Resources\KelasMkResource::scopedAccessibleUnitIds();
    expect(KurikulumTerpilih::optionsForUnits($unitIds))->toHaveKey($this->kurikulum->id);

    Livewire::test(ListKelasMks::class)
        ->loadTable()
        ->assertSee('Kurikulum Set Kelas', escape: false);
});

it('modal set banyak kelas menampilkan daftar mata kuliah per semester ke', function () {
    $component = Livewire::test(ListKelasMks::class)
        ->loadTable()
        ->mountAction('setBanyakKelas');

    $bySemester = $component->get('mountedActions')[0]['data']['penawaran_by_semester'] ?? [];

    expect($bySemester['3'] ?? [])->toHaveCount(2)
        ->and(collect($bySemester['3'])->pluck('label_mk')->sort()->values()->all())
        ->toBe(['Basis Data (UI202)', 'Statistika (UI201)'])
        ->and($bySemester['1'] ?? [])->toBe([]);
});

it('kontrol semester mencentang semua mk dan mengisi jumlah kelas', function () {
    $component = Livewire::test(ListKelasMks::class)
        ->loadTable()
        ->mountAction('setBanyakKelas');

    $barisSemester3 = $component->get('mountedActions')[0]['data']['penawaran_by_semester']['3'] ?? [];
    $fillState = [];

    foreach ($barisSemester3 as $uuid => $baris) {
        $fillState["penawaran_by_semester.3.{$uuid}.dipilih"] = true;
        $fillState["penawaran_by_semester.3.{$uuid}.jumlah_kelas"] = 2;
    }

    $component
        ->fillForm($fillState)
        ->callMountedAction()
        ->assertNotified();

    expect(KelasMk::query()->where('semester_id', $this->semester->id)->count())->toBe(4)
        ->and(KelasMk::query()->where('mk_unit_id', $this->mkUnitA->id)->pluck('kode_kelas')->sort()->values()->all())->toBe(['A', 'B']);
});

it('admin prodi dapat set banyak kelas lewat wizard', function () {
    $component = Livewire::test(ListKelasMks::class)
        ->loadTable()
        ->mountAction('setBanyakKelas');

    $mounted = $component->get('mountedActions')[0]['data'];
    $barisSemester3 = $mounted['penawaran_by_semester']['3'] ?? [];

    $fillState = [];

    foreach ($barisSemester3 as $uuid => $baris) {
        $jumlah = $baris['mk_unit_id'] === $this->mkUnitA->id ? 2 : 1;
        $fillState["penawaran_by_semester.3.{$uuid}.dipilih"] = true;
        $fillState["penawaran_by_semester.3.{$uuid}.jumlah_kelas"] = $jumlah;
    }

    $component
        ->fillForm($fillState)
        ->callMountedAction()
        ->assertNotified();

    expect(KelasMk::query()->where('semester_id', $this->semester->id)->count())->toBe(3)
        ->and(KelasMk::query()->where('mk_unit_id', $this->mkUnitA->id)->pluck('kode_kelas')->sort()->values()->all())->toBe(['A', 'B'])
        ->and(KelasMk::query()->where('mk_unit_id', $this->mkUnitB->id)->value('kode_kelas'))->toBe('A');
});

it('preview set banyak kelas menampilkan header kolom kelas a b c', function () {
    $service = app(SetBanyakKelasMkService::class);

    $html = $service->renderPreviewHtml($this->semester->id, [[
        'mk_unit_id' => $this->mkUnitA->id,
        'kode_penawaran' => 'UI201',
        'nama_mk' => 'Statistika',
        'dipilih' => true,
        'jumlah_kelas' => 3,
    ]])->toHtml();

    expect($html)->toContain('Statistika')
        ->and($html)->toContain('UI201')
        ->and($html)->toContain('>A<')
        ->and($html)->toContain('>B<')
        ->and($html)->toContain('>C<');
});
