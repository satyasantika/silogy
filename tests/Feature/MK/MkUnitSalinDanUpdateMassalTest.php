<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\ListMkUnits;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Services\MkUnitSalinExportService;
use App\Modules\MK\Services\MkUnitUpdateMassalService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();

    $this->kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Salin Update Uji',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    KurikulumTerpilih::set($this->kurikulumProdi->id);
});

it('mengekspor penawaran mk sebagai tsv tiga kolom', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kalkulus',
    ]);

    MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create([
        'kode' => 'MTK101',
        'semester_ke' => 3,
    ]);

    $teks = app(MkUnitSalinExportService::class)->exportTsv($this->prodi->id);

    expect($teks)->toBe("Kalkulus\tMTK101\t3");
});

it('update massal memperbarui kode dan semester penawaran mk', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kalkulus',
    ]);

    MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create([
        'kode' => 'LAMA',
        'semester_ke' => 1,
    ]);

    $timkur = buatTimkurProdiSalin($this->prodi);
    $this->actingAs($timkur);

    Livewire::test(ListMkUnits::class)
        ->callAction('bulkImport', [
            'rows' => 'Kalkulus|MTK101|3',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    $penawaran = MkUnit::query()->where('mk_id', $mk->id)->firstOrFail();

    expect($penawaran->kode)->toBe('MTK101')
        ->and($penawaran->semester_ke)->toBe(3);
});

it('update massal menandai duplikat bila kode dan semester sudah sama', function () {
    $service = app(MkUnitUpdateMassalService::class);

    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kalkulus',
    ]);

    MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create([
        'kode' => 'MTK101',
        'semester_ke' => 3,
    ]);

    $context = ['import_kurikulum_id' => $this->kurikulumProdi->id];

    $hasil = $service->resolveBaris([
        'nama' => 'Kalkulus',
        'kode' => 'MTK101',
        'semester_ke' => '3',
    ], $context);

    expect($hasil['status'])->toBe('duplikat');
});

it('update massal menolak nama mata kuliah yang tidak ada pada penawaran', function () {
    $service = app(MkUnitUpdateMassalService::class);

    $hasil = $service->resolveBaris([
        'nama' => 'MK Tidak Ada',
        'kode' => 'X1',
        'semester_ke' => '2',
    ], ['import_kurikulum_id' => $this->kurikulumProdi->id]);

    expect($hasil['status'])->toBe('invalid');
});

it('update massal menolak kolom wajib kosong', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kalkulus',
    ]);

    MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create([
        'kode' => 'LAMA',
        'semester_ke' => 1,
    ]);

    $timkur = buatTimkurProdiSalin($this->prodi);
    $this->actingAs($timkur);

    Livewire::test(ListMkUnits::class)
        ->callAction('bulkImport', [
            'rows' => 'Kalkulus|MTK101|',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $this->kurikulumProdi->id,
        ]);

    expect(MkUnit::query()->where('mk_id', $mk->id)->value('kode'))->toBe('LAMA');
});

function buatTimkurProdiSalin(AcademicUnit $prodi): User
{
    $user = User::create([
        'full_name' => 'Timkur Salin Uji',
        'username' => 'timkursalin'.Str::random(4),
        'email' => Str::random(8).'@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);
    $user->forceFill(['email_verified_at' => now()])->save();
    $user->assignRole(Role::where('name', 'Tim Kurikulum')->firstOrFail());

    AcademicUnitUser::create([
        'id' => (string) Str::uuid(),
        'academic_unit_id' => $prodi->id,
        'user_id' => $user->id,
        'status_pimpinan' => false,
        'status_tim_kurikulum' => true,
        'jabatan' => 'Anggota Tim Kurikulum',
    ]);

    return $user;
}
