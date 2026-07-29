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
use Filament\Actions\ActionGroup;
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

it('update massal mengikuti kurikulum yang dikerjakan meski konteks kurikulum lain dikirim', function () {
    $prodiLain = AcademicUnit::factory()
        ->studyProgram($this->prodi->parent ?? $this->prodi)
        ->create(['nama' => 'Prodi Tetangga']);

    $kurikulumLain = Kurikulum::query()->create([
        'academic_unit_id' => $prodiLain->id,
        'nama' => 'Kurikulum Prodi Lain',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $mkSaya = Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'Kalkulus']);
    $mkLain = Mk::factory()->forKurikulum($kurikulumLain)->create(['nama' => 'Kalkulus']);

    MkUnit::factory()->forMk($mkSaya)->forAcademicUnit($this->prodi)->create([
        'kode' => 'LAMA',
        'semester_ke' => 1,
    ]);
    MkUnit::factory()->forMk($mkLain)->forAcademicUnit($prodiLain)->create([
        'kode' => 'LAIN',
        'semester_ke' => 1,
    ]);

    $this->actingAs(buatTimkurProdiSalin($this->prodi));

    Livewire::test(ListMkUnits::class)
        ->callAction('bulkImport', [
            'rows' => 'Kalkulus|MTK101|3',
            'mode_duplikat' => 'lewati',
            'import_kurikulum_id' => $kurikulumLain->id,
        ]);

    expect(MkUnit::query()->where('mk_id', $mkSaya->id)->value('kode'))->toBe('MTK101')
        ->and(MkUnit::query()->where('mk_id', $mkLain->id)->value('kode'))->toBe('LAIN');
});

it('modal update massal dan salin mata kuliah menampilkan banner kurikulum yang dikerjakan', function () {
    $this->actingAs(buatTimkurProdiSalin($this->prodi));

    Livewire::test(ListMkUnits::class)
        ->mountAction('bulkImport')
        ->assertMountedActionModalSee('Kurikulum yang dikerjakan')
        ->assertMountedActionModalSee($this->kurikulumProdi->nama)
        // Select kurikulum digantikan banner identitas.
        ->assertMountedActionModalDontSee('Kurikulum (prodi)');

    Livewire::test(ListMkUnits::class)
        ->mountAction('salinMkUnit')
        ->assertMountedActionModalSee('Kurikulum yang dikerjakan')
        ->assertMountedActionModalSee($this->kurikulumProdi->nama);
});

it('salin mata kuliah dan update massal berada pada trigger grup di samping adaptasi mk', function () {
    $this->actingAs(buatTimkurProdiSalin($this->prodi));

    $halaman = Livewire::test(ListMkUnits::class)->assertSee('Alat penawaran MK');

    $aksi = (fn (): array => $this->getHeaderActions())->call($halaman->instance());

    expect($aksi[0]->getName())->toBe('adaptasiMkMassal')
        ->and($aksi[1])->toBeInstanceOf(ActionGroup::class)
        ->and(collect($aksi[1]->getActions())->map(fn ($item): string => $item->getName())->values()->all())
        ->toBe(['salinMkUnit', 'bulkImport']);
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
