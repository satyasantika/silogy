<?php

use App\Models\Role;
use App\Models\User;
use App\Modules\Auth\Filament\Resources\UserResource;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\CreateUser;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\EditUser;
use App\Modules\Auth\Filament\Resources\UserResource\Pages\ListUsers;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Institusi\Models\AcademicUnitUser;
use App\Modules\Kurikulum\Models\Kurikulum;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::where('username', 'superadmin')->first());
});

it('dapat membuat dosenfoo dengan role dosen pengampu dan penugasan prodi', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->first();
    $roleDosen = Role::where('name', 'Dosen Pengampu')->first();

    Livewire::test(CreateUser::class)
        ->fillForm([
            'full_name' => 'Dosen Foo',
            'username' => 'dosenfoo',
            'email' => 'dosenfoo@silogy.test',
            'password' => 'Silogy2026!',
            'roles' => [$roleDosen->id],
            'academicUnitUsers' => [
                [
                    'academic_unit_id' => $prodi->id,
                    'status_pimpinan' => false,
                    'status_tim_kurikulum' => false,
                    'jabatan' => 'Dosen',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('username', 'dosenfoo')->first();

    expect($user)->not->toBeNull()
        ->and($user->email)->toBe('dosenfoo@silogy.test')
        ->and($user->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and(Hash::check('Silogy2026!', $user->password))->toBeTrue();

    $pivot = AcademicUnitUser::query()
        ->where('user_id', $user->id)
        ->where('academic_unit_id', $prodi->id)
        ->first();

    expect($pivot)->not->toBeNull()
        ->and($pivot->jabatan)->toBe('Dosen')
        ->and($pivot->status_pimpinan)->toBeFalse()
        ->and($pivot->status_tim_kurikulum)->toBeFalse();
});

it('superadmin dapat mengakses daftar pengguna', function () {
    Livewire::test(ListUsers::class)
        ->assertSuccessful();
});

it('superadmin dapat membuka halaman edit pengguna', function () {
    $dosen = User::create([
        'full_name' => 'Dosen Edit',
        'username' => 'dosenedit',
        'email' => 'dosenedit@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    Livewire::test(EditUser::class, ['record' => $dosen->id])
        ->assertSuccessful()
        ->assertFormFieldExists('password');
});

it('dapat mengimpor pengguna massal lewat copypaste dengan pemisah pipe', function () {
    $rows = implode("\n", [
        'Budi Santoso|budisantoso|RahasiaKuat123|budi@silogy.test|Dosen Pengampu',
        'Siti Aminah|sitiaminah|RahasiaKuat456|siti@silogy.test|Tim Kurikulum;Dosen Pengampu',
    ]);

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati'])
        ->assertHasNoActionErrors();

    $budi = User::where('username', 'budisantoso')->first();
    $siti = User::where('username', 'sitiaminah')->first();

    expect($budi)->not->toBeNull()
        ->and($budi->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and($budi->email_verified_at)->not->toBeNull()
        ->and(Hash::check('RahasiaKuat123', $budi->password))->toBeTrue()
        ->and($siti)->not->toBeNull()
        ->and($siti->hasRole(['Tim Kurikulum', 'Dosen Pengampu']))->toBeTrue();
});

it('dapat mengimpor pengguna dari tempelan excel (pemisah tab)', function () {
    $rows = "Budi Excel\tbudiexcel\tRahasiaKuat123\tbudiexcel@silogy.test\tDosen Pengampu";

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati'])
        ->assertHasNoActionErrors();

    $budi = User::where('username', 'budiexcel')->first();

    expect($budi)->not->toBeNull()
        ->and($budi->hasRole('Dosen Pengampu'))->toBeTrue();
});

it('baris invalid dilewati sementara baris valid tetap diimpor', function () {
    $rows = implode("\n", [
        'Budi Santoso|budisantoso|RahasiaKuat123|budi@silogy.test|Dosen Pengampu',
        'Baris Rusak|cuma-dua-kolom',
        'Role Aneh|roleaneh|RahasiaKuat123|roleaneh@silogy.test|Role Tidak Ada',
    ]);

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati']);

    expect(User::where('username', 'budisantoso')->exists())->toBeTrue()
        ->and(User::where('username', 'roleaneh')->exists())->toBeFalse();
});

it('duplikat dilewati saat mode lewati dan ditimpa saat mode timpa', function () {
    $lama = User::create([
        'full_name' => 'Nama Lama',
        'username' => 'userlama',
        'email' => 'userlama@silogy.test',
        'password' => 'PasswordLama123',
    ]);

    $rows = 'Nama Baru|userlama|PasswordBaru123|userlama@silogy.test|Dosen Pengampu';

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati']);

    expect($lama->fresh()->full_name)->toBe('Nama Lama');

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'timpa']);

    $lama->refresh();

    expect($lama->full_name)->toBe('Nama Baru')
        ->and(Hash::check('PasswordBaru123', $lama->password))->toBeTrue()
        ->and($lama->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and(User::where('email', 'userlama@silogy.test')->count())->toBe(1);
});

it('opsi isi nidn dari username hanya berlaku untuk baris role dosen pengampu', function () {
    $rows = implode("\n", [
        'Budi Dosen|budidosen|RahasiaKuat123|budidosen@silogy.test|Dosen Pengampu',
        'Siti Kurikulum|sitikurikulum|RahasiaKuat123|siti@silogy.test|Tim Kurikulum',
    ]);

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', [
            'rows' => $rows,
            'mode_duplikat' => 'lewati',
            'isi_nidn_dari_username' => true,
        ])
        ->assertHasNoActionErrors();

    $budi = User::where('username', 'budidosen')->firstOrFail();
    $siti = User::where('username', 'sitikurikulum')->firstOrFail();

    expect($budi->nidn)->toBe('budidosen')
        ->and($siti->nidn)->toBeNull();
});

it('nidn tetap kosong bila opsi isi nidn dari username tidak diaktifkan', function () {
    $rows = 'Budi Dosen|budidosen|RahasiaKuat123|budidosen@silogy.test|Dosen Pengampu';

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati'])
        ->assertHasNoActionErrors();

    expect(User::where('username', 'budidosen')->firstOrFail()->nidn)->toBeNull();
});

it('baris ditandai invalid bila nidn dari username bentrok dengan pengguna lain', function () {
    User::create([
        'full_name' => 'Sudah Punya NIDN',
        'username' => 'punyanidnlain',
        'email' => 'punyanidnlain@silogy.test',
        'password' => 'RahasiaKuat123',
        'nidn' => 'budidosen',
    ]);

    $rows = implode("\n", [
        'Budi Dosen|budidosen|RahasiaKuat123|budidosen@silogy.test|Dosen Pengampu',
        'Lain Dosen|laindosen|RahasiaKuat123|laindosen@silogy.test|Dosen Pengampu',
    ]);

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', [
            'rows' => $rows,
            'mode_duplikat' => 'lewati',
            'isi_nidn_dari_username' => true,
        ]);

    expect(User::where('username', 'budidosen')->exists())->toBeFalse()
        ->and(User::where('username', 'laindosen')->firstOrFail()->nidn)->toBe('laindosen');
});

it('mode timpa dengan opsi aktif ikut memperbarui nidn pengguna lama', function () {
    $lama = User::create([
        'full_name' => 'Nama Lama',
        'username' => 'dosenlama',
        'email' => 'dosenlama@silogy.test',
        'password' => 'PasswordLama123',
    ]);

    $rows = 'Nama Baru|dosenlama|PasswordBaru123|dosenlama@silogy.test|Dosen Pengampu';

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', [
            'rows' => $rows,
            'mode_duplikat' => 'timpa',
            'isi_nidn_dari_username' => true,
        ]);

    expect($lama->fresh()->nidn)->toBe('dosenlama');
});

it('preview impor menandai status baru, duplikat, dan invalid', function () {
    User::create([
        'full_name' => 'Sudah Ada',
        'username' => 'sudahada',
        'email' => 'sudahada@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    $rows = implode("\n", [
        'Orang Baru|orangbaru|RahasiaKuat123|orangbaru@silogy.test|Dosen Pengampu',
        'Sudah Ada|sudahada|RahasiaKuat123|sudahada@silogy.test|Dosen Pengampu',
        'Rusak|dua-kolom',
    ]);

    $page = new ListUsers;
    $parsed = collect($page->parseImportRaw($rows))->pluck('status');

    expect($parsed->all())->toBe(['baru', 'duplikat', 'invalid']);

    $preview = $page->renderImportPreview($rows)->toHtml();

    expect($preview)->toContain('1 baru')
        ->toContain('1 duplikat')
        ->toContain('1 invalid');
});

it('bulk action ubah status dapat mengaktifkan banyak pengguna sekaligus', function () {
    $nonaktif = collect(range(1, 3))->map(fn (int $i) => User::create([
        'full_name' => "Pengguna Nonaktif {$i}",
        'username' => "nonaktif{$i}",
        'email' => "nonaktif{$i}@silogy.test",
        'password' => 'RahasiaKuat123',
    ]));

    Livewire::test(ListUsers::class)
        ->callTableBulkAction('ubahStatus', $nonaktif->pluck('id')->all(), ['status' => 'aktif']);

    $nonaktif->each(function (User $user): void {
        expect($user->fresh()->email_verified_at)->not->toBeNull();
    });
});

it('dosen pengampu tidak dapat mengakses daftar pengguna', function () {
    $this->actingAs(User::where('username', 'dosen')->first());

    Livewire::test(ListUsers::class)
        ->assertForbidden();
});

it('tidak mengizinkan hapus user yang datanya dipakai tabel lain', function () {
    $superadmin = User::where('username', 'superadmin')->first();
    $prodi = AcademicUnit::where('type', 'study_program')->first();

    $dosen = User::create([
        'full_name' => 'Dosen Terpakai',
        'username' => 'dosenterpakai',
        'email' => 'dosenterpakai@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    Kurikulum::create([
        'academic_unit_id' => $prodi->id,
        'nama' => 'Kurikulum Uji',
        'tahun' => 2026,
        'dibuat_oleh' => $dosen->id,
    ]);

    expect($dosen->hasDependentRecords())->toBeTrue()
        ->and($superadmin->can('delete', $dosen))->toBeFalse();
});

it('mengizinkan hapus user yang datanya belum dipakai tabel lain', function () {
    $superadmin = User::where('username', 'superadmin')->first();

    $dosen = User::create([
        'full_name' => 'Dosen Bebas',
        'username' => 'dosenbebas',
        'email' => 'dosenbebas@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    expect($dosen->hasDependentRecords())->toBeFalse()
        ->and($superadmin->can('delete', $dosen))->toBeTrue();
});

it('label penugasan unit menampilkan badge pimpinan dan tim kurikulum', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->first();

    $kosong = UserResource::labelPenugasanUnit([])->toHtml();
    expect($kosong)->toContain('Penugasan baru')
        ->not->toContain('Pimpinan')
        ->not->toContain('Tim kurikulum');

    $denganBadge = UserResource::labelPenugasanUnit([
        'academic_unit_id' => $prodi->id,
        'status_pimpinan' => true,
        'status_tim_kurikulum' => true,
    ])->toHtml();

    expect($denganBadge)
        ->toContain(e($prodi->nama_lengkap))
        ->toContain('>Pimpinan</span>')
        ->toContain('>Tim kurikulum</span>');

    $hanyaPimpinan = UserResource::labelPenugasanUnit([
        'academic_unit_id' => $prodi->id,
        'status_pimpinan' => true,
        'status_tim_kurikulum' => false,
    ])->toHtml();

    expect($hanyaPimpinan)
        ->toContain('>Pimpinan</span>')
        ->not->toContain('Tim kurikulum');
});

it('setelah tambah penugasan unit, accordion repeater otomatis dilipat', function () {
    $dosen = User::create([
        'full_name' => 'Dosen Accordion',
        'username' => 'dosenaccordion',
        'email' => 'dosenaccordion@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    $test = Livewire::test(EditUser::class, ['record' => $dosen->id]);

    $repeater = $test->instance()->form->getComponent('academicUnitUsers');
    expect($repeater)->toBeInstanceOf(\Filament\Forms\Components\Repeater::class)
        ->and($repeater->isCollapsed())->toBeTrue();

    $test->callFormComponentAction('academicUnitUsers', 'add');

    $repeaterSetelah = $test->instance()->form->getComponent('academicUnitUsers');
    expect($repeaterSetelah->isCollapsed())->toBeTrue()
        ->and(count($repeaterSetelah->getRawState() ?? []))->toBe(1);
});

it('card Akun default dilipat dan Permission dilebur ke card Role', function () {
    $dosen = User::create([
        'full_name' => 'Dosen Section',
        'username' => 'dosensection',
        'email' => 'dosensection@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    Livewire::test(EditUser::class, ['record' => $dosen->id])
        ->assertSuccessful()
        ->assertSee('(username, email, password)', escape: false)
        ->assertSee('Permission', escape: false)
        ->assertDontSee('Permission Langsung', escape: false)
        ->assertFormFieldExists('roles')
        ->assertFormFieldExists('permissions')
        ->assertFormFieldExists('username');
});
