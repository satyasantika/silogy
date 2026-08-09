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
use Filament\Forms\Components\Repeater;
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
        'Budi Santoso|budi@silogy.test|RahasiaKuat123|Dosen Pengampu||budisantoso',
        'Siti Aminah|siti@silogy.test|RahasiaKuat456|Tim Kurikulum;Dosen Pengampu||sitiaminah',
    ]);

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati'])
        ->assertHasNoActionErrors();

    $budi = User::where('email', 'budi@silogy.test')->first();
    $siti = User::where('email', 'siti@silogy.test')->first();

    expect($budi)->not->toBeNull()
        ->and($budi->username)->toBe('budisantoso')
        ->and($budi->hasRole('Dosen Pengampu'))->toBeTrue()
        ->and($budi->email_verified_at)->not->toBeNull()
        ->and(Hash::check('RahasiaKuat123', $budi->password))->toBeTrue()
        ->and($siti)->not->toBeNull()
        ->and($siti->hasRole(['Tim Kurikulum', 'Dosen Pengampu']))->toBeTrue();
});

it('dapat mengimpor pengguna dari tempelan excel (pemisah tab)', function () {
    $rows = "Budi Excel\tbudiexcel@silogy.test\tRahasiaKuat123\tDosen Pengampu\t\tbudiexcel";

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati'])
        ->assertHasNoActionErrors();

    $budi = User::where('email', 'budiexcel@silogy.test')->first();

    expect($budi)->not->toBeNull()
        ->and($budi->username)->toBe('budiexcel')
        ->and($budi->hasRole('Dosen Pengampu'))->toBeTrue();
});

it('baris invalid dilewati sementara baris valid tetap diimpor', function () {
    $rows = implode("\n", [
        'Budi Santoso|budi@silogy.test|RahasiaKuat123|Dosen Pengampu',
        'Baris Rusak|cuma-dua-kolom',
        'Role Aneh|roleaneh@silogy.test|RahasiaKuat123|Role Tidak Ada',
    ]);

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati']);

    expect(User::where('email', 'budi@silogy.test')->exists())->toBeTrue()
        ->and(User::where('email', 'roleaneh@silogy.test')->exists())->toBeFalse();
});

it('duplikat dilewati saat mode lewati dan ditimpa saat mode timpa', function () {
    $lama = User::create([
        'full_name' => 'Nama Lama',
        'username' => 'userlama',
        'email' => 'userlama@silogy.test',
        'password' => 'PasswordLama123',
    ]);

    $rows = 'Nama Baru|userlama@silogy.test|PasswordBaru123|Dosen Pengampu||userlama';

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

it('impor massal: hanya name/email/password/role wajib, sisanya NULL bila kosong', function () {
    $rows = 'Dosen Minimal|dosenminimal@silogy.test|RahasiaKuat123|Dosen Pengampu';

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati'])
        ->assertHasNoActionErrors();

    $user = User::where('email', 'dosenminimal@silogy.test')->firstOrFail();

    expect($user->username)->toBeNull()
        ->and($user->nip)->toBeNull()
        ->and($user->nidn)->toBeNull()
        ->and($user->nuptk)->toBeNull();
});

it('impor massal: username, nip, nidn, nuptk tersimpan sesuai isian', function () {
    $rows = "Dosen Lengkap\tdosenlengkap@silogy.test\tRahasiaKuat123\tDosen Pengampu\t\tdosenlengkap\t198501012010122001\t0012345678\t1234567890123456";

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati'])
        ->assertHasNoActionErrors();

    $user = User::where('email', 'dosenlengkap@silogy.test')->firstOrFail();

    expect($user->username)->toBe('dosenlengkap')
        ->and($user->nip)->toBe('198501012010122001')
        ->and($user->nidn)->toBe('0012345678')
        ->and($user->nuptk)->toBe('1234567890123456');
});

it('impor massal: baris invalid bila nip bentrok dengan pengguna lain', function () {
    User::create([
        'full_name' => 'Sudah Punya NIP',
        'email' => 'sudahpunyanip@silogy.test',
        'password' => 'RahasiaKuat123',
        'nip' => '198501012010122001',
    ]);

    $rows = "Dosen Baru\tdosenbaru@silogy.test\tRahasiaKuat123\tDosen Pengampu\t\t\t198501012010122001";

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati']);

    expect(User::where('email', 'dosenbaru@silogy.test')->exists())->toBeFalse();
});

it('impor massal: kode prodi merelasikan pengguna ke unit dengan status sesuai role', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();

    $rows = "Dosen Kurikulum\tdosenkurikulum@silogy.test\tRahasiaKuat123\tTim Kurikulum;Dosen Pengampu\t{$prodi->code}\tdosenkurikulum";

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati'])
        ->assertHasNoActionErrors();

    $user = User::where('email', 'dosenkurikulum@silogy.test')->firstOrFail();

    $pivot = AcademicUnitUser::query()
        ->where('user_id', $user->id)
        ->where('academic_unit_id', $prodi->id)
        ->first();

    expect($pivot)->not->toBeNull()
        ->and($pivot->status_tim_kurikulum)->toBeTrue()
        ->and($pivot->status_pimpinan)->toBeFalse();
});

it('impor massal: beberapa kode prodi dipisah koma membuat satu pivot per prodi', function () {
    $prodiA = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $fakultas = AcademicUnit::where('type', 'faculty')->firstOrFail();
    $prodiB = AcademicUnit::factory()->studyProgram($fakultas)->create([
        'nama' => 'Program Studi Kedua',
        'code' => '9001',
    ]);

    $rows = "Dosen Multi\tdosenmulti@silogy.test\tRahasiaKuat123\tDosen Pengampu\t{$prodiA->code},{$prodiB->code}\tdosenmulti";

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati'])
        ->assertHasNoActionErrors();

    $user = User::where('email', 'dosenmulti@silogy.test')->firstOrFail();

    expect(AcademicUnitUser::query()->where('user_id', $user->id)->count())->toBe(2)
        ->and(AcademicUnitUser::query()->where('user_id', $user->id)->where('academic_unit_id', $prodiA->id)->exists())->toBeTrue()
        ->and(AcademicUnitUser::query()->where('user_id', $user->id)->where('academic_unit_id', $prodiB->id)->exists())->toBeTrue();
});

it('impor massal: tanpa kode prodi tidak membuat relasi unit apa pun', function () {
    $rows = "Dosen Tanpa Prodi\tdosentanpaprodi@silogy.test\tRahasiaKuat123\tDosen Pengampu";

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati'])
        ->assertHasNoActionErrors();

    $user = User::where('email', 'dosentanpaprodi@silogy.test')->firstOrFail();

    expect(AcademicUnitUser::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('impor massal: kode prodi tidak ditemukan membuat baris invalid', function () {
    $rows = "Dosen Salah Prodi\tdosensalahprodi@silogy.test\tRahasiaKuat123\tDosen Pengampu\tKODE-TIDAK-ADA";

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati']);

    expect(User::where('email', 'dosensalahprodi@silogy.test')->exists())->toBeFalse();
});

it('impor massal mode timpa: kode prodi bersifat additive, tidak menghapus relasi unit lain', function () {
    $prodiA = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $fakultas = AcademicUnit::where('type', 'faculty')->firstOrFail();
    $prodiB = AcademicUnit::factory()->studyProgram($fakultas)->create([
        'nama' => 'Program Studi Ketiga',
        'code' => '9002',
    ]);

    $user = User::create([
        'full_name' => 'Dosen Additive',
        'username' => 'dosenadditive',
        'email' => 'dosenadditive@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    AcademicUnitUser::query()->create([
        'user_id' => $user->id,
        'academic_unit_id' => $prodiA->id,
        'status_pimpinan' => false,
        'status_tim_kurikulum' => false,
    ]);

    $rows = "Dosen Additive\tdosenadditive@silogy.test\tRahasiaKuat123\tDosen Pengampu\t{$prodiB->code}\tdosenadditive";

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'timpa'])
        ->assertHasNoActionErrors();

    expect(AcademicUnitUser::query()->where('user_id', $user->id)->where('academic_unit_id', $prodiA->id)->exists())->toBeTrue()
        ->and(AcademicUnitUser::query()->where('user_id', $user->id)->where('academic_unit_id', $prodiB->id)->exists())->toBeTrue()
        ->and(AcademicUnitUser::query()->where('user_id', $user->id)->count())->toBe(2);
});

it('impor massal: role tanpa tim kurikulum/pimpinan tetap membuat relasi unit dengan kedua status false', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();

    $rows = "Dosen Biasa\tdosenbiasa@silogy.test\tRahasiaKuat123\tDosen Pengampu\t{$prodi->code}";

    Livewire::test(ListUsers::class)
        ->callAction('bulkImport', ['rows' => $rows, 'mode_duplikat' => 'lewati'])
        ->assertHasNoActionErrors();

    $user = User::where('email', 'dosenbiasa@silogy.test')->firstOrFail();
    $pivot = AcademicUnitUser::query()->where('user_id', $user->id)->where('academic_unit_id', $prodi->id)->first();

    expect($pivot)->not->toBeNull()
        ->and($pivot->status_pimpinan)->toBeFalse()
        ->and($pivot->status_tim_kurikulum)->toBeFalse();
});

it('preview impor menandai status baru, duplikat, dan invalid', function () {
    User::create([
        'full_name' => 'Sudah Ada',
        'username' => 'sudahada',
        'email' => 'sudahada@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    $rows = implode("\n", [
        'Orang Baru|orangbaru@silogy.test|RahasiaKuat123|Dosen Pengampu',
        'Sudah Ada|sudahada@silogy.test|RahasiaKuat123|Dosen Pengampu',
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

it('setelah tambah penugasan unit, kartu penugasan baru otomatis terbuka', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();

    $dosen = User::create([
        'full_name' => 'Dosen Accordion',
        'username' => 'dosenaccordion',
        'email' => 'dosenaccordion@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    AcademicUnitUser::query()->create([
        'user_id' => $dosen->id,
        'academic_unit_id' => $prodi->id,
        'status_pimpinan' => false,
        'status_tim_kurikulum' => false,
    ]);

    $test = Livewire::test(EditUser::class, ['record' => $dosen->id]);
    $test->callFormComponentAction('academicUnitUsers', 'add');

    $repeater = $test->instance()->form->getComponent('academicUnitUsers');
    expect($repeater)->toBeInstanceOf(Repeater::class);

    $items = array_values($repeater->getItems());
    expect($items)->toHaveCount(2)
        ->and($repeater->isCollapsed($items[0]))->toBeTrue()
        ->and($repeater->isCollapsed($items[1]))->toBeFalse()
        ->and($items[1]->getComponent('academic_unit_id')->getColumnSpan())->toBe(['default' => 'full']);
});

it('hapus penugasan unit meminta konfirmasi sebelum dijalankan', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();

    $dosen = User::create([
        'full_name' => 'Dosen Hapus Penugasan',
        'username' => 'dosenhapuspenugasan',
        'email' => 'dosenhapuspenugasan@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    AcademicUnitUser::query()->create([
        'user_id' => $dosen->id,
        'academic_unit_id' => $prodi->id,
        'status_pimpinan' => false,
        'status_tim_kurikulum' => false,
    ]);

    $test = Livewire::test(EditUser::class, ['record' => $dosen->id]);
    $repeater = $test->instance()->form->getComponent('academicUnitUsers');
    expect($repeater)->toBeInstanceOf(Repeater::class)
        ->and($repeater->getDeleteAction()->isConfirmationRequired())->toBeTrue();

    $itemKey = array_key_first($repeater->getRawState() ?? []);

    $test->mountFormComponentAction('academicUnitUsers', 'delete', ['item' => $itemKey])
        ->assertFormComponentActionMounted('academicUnitUsers', 'delete');

    expect(count($test->instance()->form->getComponent('academicUnitUsers')->getRawState() ?? []))->toBe(1);

    $modal = $test->effects['partials']['action-modals'] ?? '';
    expect($modal)->toContain('Hapus penugasan unit?')
        ->toContain('Ya, hapus')
        ->toContain(e($prodi->nama_lengkap));

    $test->callMountedFormComponentAction();

    expect(count($test->instance()->form->getComponent('academicUnitUsers')->getRawState() ?? []))->toBe(0);
});

it('opsi unit penugasan pada kartu baru mengecualikan unit yang sudah dipakai user', function () {
    $prodi = AcademicUnit::where('type', 'study_program')->firstOrFail();
    $fakultas = AcademicUnit::where('type', 'faculty')->firstOrFail();

    $dosen = User::create([
        'full_name' => 'Dosen Penugasan',
        'username' => 'dosenpenugasan',
        'email' => 'dosenpenugasan@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    AcademicUnitUser::query()->create([
        'user_id' => $dosen->id,
        'academic_unit_id' => $prodi->id,
        'status_pimpinan' => false,
        'status_tim_kurikulum' => false,
        'jabatan' => 'Dosen',
    ]);

    $test = Livewire::test(EditUser::class, ['record' => $dosen->id]);
    $test->callFormComponentAction('academicUnitUsers', 'add');

    $repeater = $test->instance()->form->getComponent('academicUnitUsers');
    expect($repeater)->toBeInstanceOf(Repeater::class);

    $items = $repeater->getItems();
    expect($items)->toHaveCount(2);

    $kartuLama = array_values($items)[0]->getComponent('academic_unit_id');
    $kartuBaru = array_values($items)[1]->getComponent('academic_unit_id');

    expect($kartuLama->getOptions())
        ->toHaveKey($prodi->id)
        ->toHaveKey($fakultas->id)
        ->and($kartuBaru->getOptions())
        ->not->toHaveKey($prodi->id)
        ->toHaveKey($fakultas->id);
});

it('tombol tambah penugasan unit nonaktif bila semua unit sudah dipakai', function () {
    $dosen = User::create([
        'full_name' => 'Dosen Penuh Unit',
        'username' => 'dosenpenuhunit',
        'email' => 'dosenpenuhunit@silogy.test',
        'password' => 'RahasiaKuat123',
    ]);

    foreach (AcademicUnit::query()->pluck('id') as $unitId) {
        AcademicUnitUser::query()->create([
            'user_id' => $dosen->id,
            'academic_unit_id' => $unitId,
            'status_pimpinan' => false,
            'status_tim_kurikulum' => false,
        ]);
    }

    $test = Livewire::test(EditUser::class, ['record' => $dosen->id]);
    $repeater = $test->instance()->form->getComponent('academicUnitUsers');

    expect($repeater->isAddable())->toBeFalse();
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
        ->assertSee('(email dan password wajib, username opsional)', escape: false)
        ->assertSee('Permission', escape: false)
        ->assertDontSee('Permission Langsung', escape: false)
        ->assertFormFieldExists('roles')
        ->assertFormFieldExists('permissions')
        ->assertFormFieldExists('username');
});
