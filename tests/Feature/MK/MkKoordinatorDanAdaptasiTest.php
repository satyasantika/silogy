<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Filament\Resources\KelasMkResource\Pages\CreateKelasMk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Filament\Resources\CpmkResource;
use App\Modules\MK\Filament\Resources\MkResource\Pages\EditMk;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\CreateMkUnit;
use App\Modules\MK\Filament\Resources\MkUnitResource\Pages\ListMkUnits;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);

    $this->univ = AcademicUnit::query()->where('type', 'university')->first();
    $this->fakultas = AcademicUnit::query()->where('type', 'faculty')->first();
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->first();
    $this->timkur = User::query()->where('username', 'timkur')->first();
    $this->korma = User::query()->where('username', 'korma')->first();
});

it('seeder menyediakan tim kurikulum terpisah per level dan dosentimkur lintas level', function () {
    $adaTimkur = fn (string $username, AcademicUnit $unit): bool => AcademicUnitUser::query()
        ->where('user_id', User::query()->where('username', $username)->firstOrFail()->id)
        ->where('academic_unit_id', $unit->id)
        ->where('status_tim_kurikulum', true)
        ->exists();

    expect($adaTimkur('timkur', $this->prodi))->toBeTrue()
        ->and($adaTimkur('timkurfak', $this->fakultas))->toBeTrue()
        ->and($adaTimkur('timkuruniv', $this->univ))->toBeTrue();

    $dosenTimkur = User::query()->where('username', 'dosentimkur')->firstOrFail();

    expect($dosenTimkur->hasRole(['Dosen Pengampu', 'Tim Kurikulum']))->toBeTrue();

    foreach ([$this->prodi, $this->fakultas, $this->univ] as $unit) {
        expect($adaTimkur('dosentimkur', $unit))->toBeTrue();
    }
});

it('tim kurikulum dapat menetapkan koordinator pada mk', function () {
    $mk = Mk::factory()->create(['academic_unit_id' => $this->prodi->id]);

    $this->actingAs($this->timkur);

    Livewire::test(EditMk::class, ['record' => $mk->id])
        ->fillForm(['koordinator_mk_id' => $this->korma->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($mk->fresh()->koordinator_mk_id)->toBe($this->korma->id);
});

it('kelas mk baru mewarisi koordinator default dari mk', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);
    $mkUnit = MkUnit::factory()->forMk($mk)->forAcademicUnit($this->prodi)->create();
    $semester = Semester::query()->where('status_aktif', true)->first();

    $this->actingAs(User::where('username', 'adminprodi')->first());

    Livewire::test(CreateKelasMk::class)
        ->fillForm([
            'mk_unit_id' => $mkUnit->id,
            'semester_id' => $semester->id,
            'kode_kelas' => 'A',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $kelas = KelasMk::query()->where('mk_unit_id', $mkUnit->id)->first();

    expect($kelas)->not->toBeNull()
        ->and($kelas->koordinator_mk_id)->toBe($this->korma->id);
});

it('koordinator mendapat scope mk sebelum kelas pertama dibuat', function () {
    $mk = Mk::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'koordinator_mk_id' => $this->korma->id,
    ]);

    expect(CpmkResource::scopedKoordinatorMkIds($this->korma)->contains($mk->id))->toBeTrue()
        ->and(CpmkResource::userCanManageMkAsKoordinator($this->korma, $mk->id))->toBeTrue();
});

it('adaptasi mk massal mengunduh mk dari universitas fakultas dan prodi sekaligus', function () {
    $kurikulumUniv = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Kur Univ Adaptasi',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $kurikulumFak = Kurikulum::query()->create([
        'academic_unit_id' => $this->fakultas->id,
        'nama' => 'Kur Fak Adaptasi',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kur Prodi Adaptasi',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    Mk::factory()->create(['academic_unit_id' => $this->univ->id, 'nama' => 'MK Universitas']);
    Mk::factory()->create(['academic_unit_id' => $this->fakultas->id, 'nama' => 'MK Fakultas']);
    Mk::factory()->create(['academic_unit_id' => $this->prodi->id, 'nama' => 'MK Prodi']);

    $timkurProdi = buatTimkurProdi($this->prodi);
    $this->actingAs($timkurProdi);

    Livewire::test(ListMkUnits::class)
        ->callAction('adaptasiMkMassal', [
            'adaptasi_unit_id' => $this->prodi->id,
            'kurikulum_univ_id' => $kurikulumUniv->id,
            'kurikulum_fakultas_id' => $kurikulumFak->id,
            'kurikulum_prodi_id' => $kurikulumProdi->id,
            'mode_duplikat' => 'lewati',
        ]);

    expect(MkUnit::query()->where('academic_unit_id', $this->prodi->id)->count())->toBe(3);
});

it('adaptasi mk massal memakai prodi kurikulum yang dikerjakan tanpa memilih unit penawaran', function () {
    $kurikulumUniv = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Kur Univ Unit Otomatis',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kur Prodi Unit Otomatis',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    Mk::factory()->forKurikulum($kurikulumUniv)->create(['nama' => 'Bahasa Indonesia']);

    $this->actingAs(buatTimkurProdi($this->prodi));
    KurikulumTerpilih::set($kurikulumProdi->id);

    Livewire::test(ListMkUnits::class)
        ->callAction('adaptasiMkMassal', [
            'kurikulum_univ_id' => $kurikulumUniv->id,
            'mode_duplikat' => 'lewati',
        ]);

    $penawaran = MkUnit::query()->where('kurikulum_id', $kurikulumProdi->id)->get();

    expect($penawaran)->toHaveCount(1)
        ->and($penawaran->first()->academic_unit_id)->toBe($this->prodi->id);
});

it('modal adaptasi menonaktifkan pilihan kurikulum universitas dan fakultas yang belum ada', function () {
    $kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kur Prodi Tanpa Induk',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->actingAs(buatTimkurProdi($this->prodi));
    KurikulumTerpilih::set($kurikulumProdi->id);

    $halaman = Livewire::test(ListMkUnits::class)->mountAction('adaptasiMkMassal');

    $halaman
        ->assertMountedActionModalSee('Kurikulum yang dikerjakan')
        ->assertMountedActionModalSee($kurikulumProdi->nama)
        ->assertMountedActionModalSee($this->prodi->nama)
        ->assertMountedActionModalSee('Belum ada kurikulum pada universitas induk prodi ini')
        ->assertMountedActionModalSee('Belum ada kurikulum pada fakultas induk prodi ini');

    // getMountedActionSchema() protected; ikat closure ke halaman agar bisa
    // memeriksa state disabled field tanpa menebak markup select Filament.
    $fields = (fn (): array => $this->getMountedActionSchema()->getFlatFields())
        ->call($halaman->instance());

    expect($fields['kurikulum_univ_id']->isDisabled())->toBeTrue()
        ->and($fields['kurikulum_fakultas_id']->isDisabled())->toBeTrue()
        ->and($fields['kurikulum_prodi_id']->isDisabled())->toBeFalse();
});

it('tombol adaptasi dan pilihan duplikat tersembunyi sampai pratinjau memuat mk', function () {
    $kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kur Prodi Tanpa MK',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $this->actingAs(buatTimkurProdi($this->prodi));
    KurikulumTerpilih::set($kurikulumProdi->id);

    Livewire::test(ListMkUnits::class)
        ->mountAction('adaptasiMkMassal')
        ->assertMountedActionModalDontSee('Adaptasi sekarang')
        ->assertMountedActionModalDontSee('Tindakan untuk data duplikat')
        ->assertMountedActionModalSee('Tidak ada mata kuliah aktif pada kurikulum sumber');
});

it('pratinjau adaptasi membaca mk sumber dan menampilkan tombol adaptasi', function () {
    $kurikulumUniv = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Kur Univ Pratinjau',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kur Prodi Pratinjau',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    Mk::factory()->forKurikulum($kurikulumUniv)->create(['nama' => 'Pendidikan Kewarganegaraan']);

    $this->actingAs(buatTimkurProdi($this->prodi));
    KurikulumTerpilih::set($kurikulumProdi->id);

    Livewire::test(ListMkUnits::class)
        ->mountAction('adaptasiMkMassal')
        ->assertMountedActionModalSee('Pendidikan Kewarganegaraan')
        ->assertMountedActionModalSee('Adaptasi sekarang')
        ->assertMountedActionModalDontSee('Tindakan untuk data duplikat');
});

it('pilihan tindakan duplikat muncul bila mk sumber sudah pernah ditawarkan', function () {
    $kurikulumUniv = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Kur Univ Duplikat',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kur Prodi Duplikat',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $mkUniv = Mk::factory()->forKurikulum($kurikulumUniv)->create(['nama' => 'Filsafat Ilmu']);

    MkUnit::factory()->forMk($mkUniv)->forAcademicUnit($this->prodi)->create([
        'kurikulum_id' => $kurikulumProdi->id,
        'kode' => 'FI-101',
    ]);

    $this->actingAs(buatTimkurProdi($this->prodi));
    KurikulumTerpilih::set($kurikulumProdi->id);

    Livewire::test(ListMkUnits::class)
        ->mountAction('adaptasiMkMassal')
        ->assertMountedActionModalSee('Tindakan untuk data duplikat')
        ->assertMountedActionModalSee('Nasib duplikat mengikuti pilihan di bawah')
        ->assertMountedActionModalSee('Adaptasi sekarang');
});

it('pratinjau adaptasi memuat ulang html penuh saat sumber diubah setelah mount (bukan partial yang tidak match)', function () {
    $kurikulumUnivLama = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Kur Univ Lama',
        'tahun' => 2025,
        'is_active' => true,
    ]);
    $kurikulumUnivBaru = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Kur Univ Baru',
        'tahun' => 2026,
        'is_active' => false,
    ]);
    $kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kur Prodi Reaktif',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    Mk::factory()->forKurikulum($kurikulumUnivBaru)->create(['nama' => 'MK Univ Baru Reaktif']);

    $this->actingAs(buatTimkurProdi($this->prodi));
    KurikulumTerpilih::set($kurikulumProdi->id);

    $halaman = Livewire::test(ListMkUnits::class)->mountAction('adaptasiMkMassal');

    $halaman->set('mountedActions.0.data.kurikulum_univ_id', $kurikulumUnivBaru->id);

    expect($halaman->effects)->toHaveKey('html');
});

it('mengosongkan pilihan sumber menghilangkan mk sumber itu dari pratinjau', function () {
    $kurikulumUniv = Kurikulum::query()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Kur Univ Dikosongkan',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kur Prodi Dikosongkan',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    Mk::factory()->forKurikulum($kurikulumUniv)->create(['nama' => 'MK Harus Hilang']);

    $this->actingAs(buatTimkurProdi($this->prodi));
    KurikulumTerpilih::set($kurikulumProdi->id);

    $halaman = Livewire::test(ListMkUnits::class)
        ->mountAction('adaptasiMkMassal')
        ->assertMountedActionModalSee('MK Harus Hilang');

    $halaman->set('mountedActions.0.data.kurikulum_univ_id', null);

    // Bukan assertMountedActionModalDontSee() — forceRender() (lihat fix
    // HasAdaptasiMkMassal) membuat response ini render 'html' penuh, bukan
    // partial 'action-modals.*' yang dicari helper itu. Cek langsung ke
    // effects['html'] mentah.
    expect($halaman->effects['html'] ?? '')->not->toContain('MK Harus Hilang');
});

it('tim kurikulum prodi dapat mengadaptasi mk universitas dengan kode dan semester sendiri', function () {
    $mkUniv = Mk::factory()->create([
        'academic_unit_id' => $this->univ->id,
        'nama' => 'Pendidikan Pancasila',
    ]);

    Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Prodi Adaptasi MK',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $timkurProdi = buatTimkurProdi($this->prodi);
    $this->actingAs($timkurProdi);

    Livewire::test(CreateMkUnit::class)
        ->fillForm([
            'academic_unit_id' => $this->prodi->id,
            'mk_id' => $mkUniv->id,
            'kode' => 'PMT-UNV101',
            'semester_ke' => 3,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $penawaran = MkUnit::query()
        ->where('mk_id', $mkUniv->id)
        ->where('academic_unit_id', $this->prodi->id)
        ->first();

    expect($penawaran)->not->toBeNull()
        ->and($penawaran->kode)->toBe('PMT-UNV101')
        ->and($penawaran->semester_ke)->toBe(3);
});

it('tim kurikulum prodi dapat mengadaptasi mk fakultas', function () {
    $mkFak = Mk::factory()->create([
        'academic_unit_id' => $this->fakultas->id,
        'nama' => 'Metodologi Penelitian Pendidikan',
    ]);

    Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Prodi Adaptasi MK Fakultas',
        'tahun' => 2026,
        'is_active' => true,
    ]);

    $timkurProdi = buatTimkurProdi($this->prodi);
    $this->actingAs($timkurProdi);

    Livewire::test(CreateMkUnit::class)
        ->fillForm([
            'academic_unit_id' => $this->prodi->id,
            'mk_id' => $mkFak->id,
            'kode' => 'PMT-FAK101',
            'semester_ke' => 5,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(
        MkUnit::query()
            ->where('mk_id', $mkFak->id)
            ->where('academic_unit_id', $this->prodi->id)
            ->exists()
    )->toBeTrue();
});

it('tim kurikulum prodi tidak dapat membuat penawaran untuk unit lain', function () {
    $mkUniv = Mk::factory()->create(['academic_unit_id' => $this->univ->id]);

    $timkurProdi = buatTimkurProdi($this->prodi);
    $this->actingAs($timkurProdi);

    Livewire::test(CreateMkUnit::class)
        ->fillForm([
            'academic_unit_id' => $this->fakultas->id,
            'mk_id' => $mkUniv->id,
            'kode' => 'ILEGAL-1',
            'semester_ke' => 1,
        ])
        ->call('create')
        ->assertHasFormErrors(['academic_unit_id']);

    expect(MkUnit::query()->where('kode', 'ILEGAL-1')->exists())->toBeFalse();
});

it('mk yang sama tidak dapat ditawarkan dua kali pada unit yang sama', function () {
    $mkUniv = Mk::factory()->create(['academic_unit_id' => $this->univ->id]);
    MkUnit::factory()->forMk($mkUniv)->forAcademicUnit($this->prodi)->create(['kode' => 'SUDAH-ADA']);

    $timkurProdi = buatTimkurProdi($this->prodi);
    $this->actingAs($timkurProdi);

    Livewire::test(CreateMkUnit::class)
        ->fillForm([
            'academic_unit_id' => $this->prodi->id,
            'mk_id' => $mkUniv->id,
            'kode' => 'KODE-LAIN',
            'semester_ke' => 2,
        ])
        ->call('create')
        ->assertHasFormErrors(['mk_id']);
});

function buatTimkurProdi(AcademicUnit $prodi): User
{
    $user = User::create([
        'full_name' => 'Timkur Prodi Uji',
        'username' => 'timkurprodi'.Str::random(4),
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
