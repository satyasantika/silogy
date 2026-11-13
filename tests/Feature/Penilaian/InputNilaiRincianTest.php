<?php

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Penilaian\Filament\Pages\InputNilai;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\RincianNilaiKelas;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Services\DataLaporanKelasService;
use App\Modules\Penilaian\Services\InputNilaiMatrixClipboardService;
use App\Modules\Penilaian\Services\RincianNilaiService;
use App\Modules\Penilaian\Services\ValidasiNilaiService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

require_once __DIR__.'/../../Support/KelasValidasiFixture.php';

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed([AcademicUnitSeeder::class, RolePermissionSeeder::class, SemesterSeeder::class, EvaluasiSeeder::class]);

    $this->dosen = User::where('username', 'dosen')->firstOrFail();
    $this->actingAs($this->dosen);
    $this->data = siapkanKelasDuaSubcpmk($this->dosen);
    $this->kelas = $this->data['kelas'];
    [$this->kmm1, $this->kmm2] = $this->data['kmm'];
    $this->komponenId = $this->data['komponen']->id;
});

function halamanRinci(KelasMk $kelas)
{
    return Livewire::test(InputNilai::class)->set('kelasMkId', $kelas->id);
}

function nilaiPivotDb(string $pivotId, string $kmmId): ?float
{
    $nilai = NilaiMahasiswa::query()
        ->where('subcpmk_komponenpenilaian_id', $pivotId)
        ->where('kelas_mk_mahasiswa_id', $kmmId)
        ->value('nilai');

    return $nilai === null ? null : (float) $nilai;
}

/** Menambah asesmen kedua (satu Sub-CPMK saja) dan membagi bobot 60/40. */
function tambahAsesmenTunggal(array $data): array
{
    $semesterId = semesterAsesmen($data['komponen']);
    $mkId = $data['komponen']->mk_id;
    komponenUntukSemester($mkId, $semesterId, ['kode' => 'Asesmen01', 'bobot' => 60]);
    $komponen2 = komponenUntukSemester($mkId, $semesterId, ['kode' => 'Asesmen02', 'nama' => 'Tugas', 'bobot' => 40]);
    $pivot = SubcpmkKomponenPenilaian::query()->create([
        'semester_id' => $semesterId,
        'subcpmk_id' => $data['pivot']->subcpmk_id,
        'komponen_penilaian_id' => $komponen2->id,
        'bobot' => 100,
    ]);

    return [$komponen2, $pivot];
}

it('rinciAsesmen membuat penanda dan menampilkan kepala sub-kolom per Sub-CPMK', function () {
    halamanRinci($this->kelas)
        ->assertSee('Rinci per Sub-CPMK')
        ->assertSeeHtml('data-silogy="rinci-asesmen"')
        ->call('rinciAsesmen', $this->komponenId)
        ->assertSet('rincian.'.$this->komponenId.'.0.subcpmk_kode', 'SUB-A')
        ->assertSee('Asesmen01/SUB-A')
        ->assertSee('Asesmen01/SUB-B')
        ->assertSee('Gabungkan kembali')
        ->assertDontSee('Rinci per Sub-CPMK');

    expect(RincianNilaiKelas::query()->where('kelas_mk_id', $this->kelas->id)->count())->toBe(1);
});

it('menyimpan skor per Sub-CPMK menulis tiap pivot, nilai akhir tertimbang, hasil Sub-CPMK berbeda', function () {
    $halaman = halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);

    $halaman
        ->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot']->id}", '80')
        ->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot2']->id}", '60')
        ->call('save')
        ->assertHasNoErrors();

    expect(nilaiPivotDb($this->data['pivot']->id, $this->kmm1->id))->toBe(80.0)
        ->and(nilaiPivotDb($this->data['pivot2']->id, $this->kmm1->id))->toBe(60.0)
        ->and((float) $this->kmm1->fresh()->nilai_angka)->toBe(65.0);

    $hasil = DB::table('hasil_subcpmk')
        ->where('kelas_mk_mahasiswa_id', $this->kmm1->id)
        ->pluck('nilai_akhir', 'subcpmk_id');

    expect($hasil)->toHaveCount(2)
        ->and((float) $hasil[$this->data['pivot']->subcpmk_id])->not->toBe((float) $hasil[$this->data['pivot2']->subcpmk_id]);
});

it('mengubah sub-kolom menghitung ulang nilai asesmen di layar sebelum disimpan', function () {
    halamanRinci($this->kelas)
        ->call('rinciAsesmen', $this->komponenId)
        ->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot']->id}", '80')
        ->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot2']->id}", '60')
        ->assertSet("nilai.{$this->kmm1->id}.{$this->komponenId}", '65.00');
});

it('skor di atas 100 pada sub-kolom ditolak dan basis data tidak berubah', function () {
    $halaman = halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);

    $halaman
        ->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot2']->id}", '101')
        ->call('save')
        ->assertHasErrors(["nilaiRinci.{$this->kmm1->id}.{$this->data['pivot2']->id}"]);

    expect(nilaiPivotDb($this->data['pivot']->id, $this->kmm1->id))->toBe(80.0)
        ->and(nilaiPivotDb($this->data['pivot2']->id, $this->kmm1->id))->toBe(80.0);
});

it('mengosongkan satu sub-kolom hanya mereset pivot itu', function () {
    $halaman = halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);

    $halaman
        ->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot2']->id}", '')
        ->call('save');

    expect(nilaiPivotDb($this->data['pivot']->id, $this->kmm1->id))->toBe(80.0)
        ->and(nilaiPivotDb($this->data['pivot2']->id, $this->kmm1->id))->toBeNull();
});

it('asesmen lain yang tidak dirinci tetap fan-out ke semua pivotnya', function () {
    [$komponen2, $pivot2tunggal] = tambahAsesmenTunggal($this->data);
    $halaman = halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);

    $halaman
        ->set("nilai.{$this->kmm1->id}.{$komponen2->id}", '90')
        ->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot']->id}", '70')
        ->call('save')
        ->assertHasNoErrors();

    expect(nilaiPivotDb($pivot2tunggal->id, $this->kmm1->id))->toBe(90.0)
        ->and(nilaiPivotDb($this->data['pivot']->id, $this->kmm1->id))->toBe(70.0)
        ->and(nilaiPivotDb($this->data['pivot2']->id, $this->kmm1->id))->toBe(80.0);
});

it('asesmen tunggal tidak menawarkan tombol rinci', function () {
    [$komponen2] = tambahAsesmenTunggal($this->data);

    $html = halamanRinci($this->kelas)->html();

    expect(substr_count($html, 'data-silogy="rinci-asesmen"'))->toBe(1)
        ->and($html)->toContain("rinciAsesmen('{$this->komponenId}')")
        ->and($html)->not->toContain("rinciAsesmen('{$komponen2->id}')");
});

it('rinciAsesmen saat ada perubahan belum disimpan ditolak dan perubahan tetap utuh', function () {
    $halaman = halamanRinci($this->kelas)
        ->set("nilai.{$this->kmm1->id}.{$this->komponenId}", '55')
        ->call('rinciAsesmen', $this->komponenId)
        ->assertNotified('Simpan nilai dulu sebelum mengubah rincian.')
        ->assertSet("nilai.{$this->kmm1->id}.{$this->komponenId}", '55')
        ->assertSet('rincian', []);

    expect(RincianNilaiKelas::query()->count())->toBe(0);
});

it('gabungkanAsesmen lewat aksi Filament menolak bila ada perubahan belum disimpan', function () {
    $halaman = halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);

    $halaman
        ->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot']->id}", '10')
        ->call('gabungkanAsesmen', $this->komponenId)
        ->assertNotified('Simpan nilai dulu sebelum mengubah rincian.')
        ->assertSet("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot']->id}", '10');

    expect(RincianNilaiKelas::query()->count())->toBe(1);
});

it('Gabungkan kembali memakai konfirmasi dan menghapus penanda tanpa menggeser nilai akhir', function () {
    $halaman = halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);
    $halaman
        ->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot']->id}", '80')
        ->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot2']->id}", '60')
        ->call('save');

    expect($halaman->instance()->gabungkanAsesmenAction()->isConfirmationRequired())->toBeTrue();

    $halaman
        ->mountAction('gabungkanAsesmen', ['komponenId' => $this->komponenId])
        ->callMountedAction()
        ->assertSet('rincian', [])
        ->assertSee('Rinci per Sub-CPMK');

    expect(RincianNilaiKelas::query()->count())->toBe(0)
        ->and((float) $this->kmm1->fresh()->nilai_angka)->toBe(65.0);
});

it('dosen lain tidak boleh menyalakan atau menggabungkan rincian (403)', function () {
    $halaman = halamanRinci($this->kelas);
    $lain = User::factory()->create();
    $lain->assignRole('Dosen Pengampu');
    $this->actingAs($lain);

    $halaman->call('rinciAsesmen', $this->komponenId)->assertForbidden();

    expect(RincianNilaiKelas::query()->count())->toBe(0);
});

it('mode hanya-baca menolak rincian dan tidak merender tombolnya', function () {
    Gate::before(fn ($user, string $ability) => $ability === 'inputNilai' ? false : null);

    Livewire::test(InputNilai::class)
        ->set('kelasMkId', $this->kelas->id)
        ->assertSet('bolehUbahNilai', false)
        ->assertDontSeeHtml('data-silogy="rinci-asesmen"')
        ->assertDontSee('Rinci per Sub-CPMK')
        ->call('rinciAsesmen', $this->komponenId)
        ->assertForbidden();

    expect(RincianNilaiKelas::query()->count())->toBe(0);
});

it('pemetaan berubah sampai tinggal satu pivot: halaman tidak galat dan kolom kembali tunggal', function () {
    app(RincianNilaiService::class)->nyalakan($this->kelas, $this->data['komponen'], $this->dosen);
    $this->data['pivot2']->delete();

    halamanRinci($this->kelas)
        ->assertOk()
        ->assertSet('rincian', [])
        ->assertDontSee('Asesmen01/SUB-A')
        ->assertDontSeeHtml('data-silogy="rinci-asesmen"')
        ->call('save')
        ->assertHasNoErrors();
});

it('sisa state rincian yang basi tidak membuat save galat setelah pemetaan menyusut', function () {
    $halaman = halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);
    $halaman->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot']->id}", '70');
    $this->data['pivot2']->delete();

    $halaman->call('save')->assertHasNoErrors();
});

it('tempel dengan kepala sub-kolom saja mengubah hanya pivot itu', function () {
    $halaman = halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);
    $nim1 = $this->kmm1->mahasiswa->nim;
    $nim2 = $this->kmm2->mahasiswa->nim;

    $halaman->call('applyTempel', "NIM\tAsesmen01/SUB-A\n{$nim1}\t90\n{$nim2}\t50")
        ->call('save')
        ->assertHasNoErrors();

    expect(nilaiPivotDb($this->data['pivot']->id, $this->kmm1->id))->toBe(90.0)
        ->and(nilaiPivotDb($this->data['pivot']->id, $this->kmm2->id))->toBe(50.0)
        ->and(nilaiPivotDb($this->data['pivot2']->id, $this->kmm1->id))->toBe(80.0)
        ->and(nilaiPivotDb($this->data['pivot2']->id, $this->kmm2->id))->toBe(60.0);
});

it('tempel dengan kepala asesmen dirinci tidak menimpa sub-kolom dan dilaporkan sebagai galat', function () {
    $halaman = halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);
    $nim1 = $this->kmm1->mahasiswa->nim;
    $nim2 = $this->kmm2->mahasiswa->nim;

    $halaman->call('applyTempel', "NIM\tAsesmen01\n{$nim1}\t10\n{$nim2}\t20")
        ->assertSet("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot']->id}", '80.00')
        ->assertSet("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot2']->id}", '80.00')
        ->assertNotified();

    expect($halaman->get('nilaiRinci')[$this->kmm2->id][$this->data['pivot']->id])->toBe('60.00');
});

it('layanan tempel melaporkan kepala kolom yang ditolak sebagai galat tanpa jatuh ke urutan posisi', function () {
    $rows = [['id' => 'k1', 'nim' => '111', 'nama' => 'A']];
    $kolom = [['id' => 'p:1', 'label' => 'UTS/SUB-1A']];

    $hasil = app(InputNilaiMatrixClipboardService::class)->applyPaste(
        "NIM\tUTS\n111\t90",
        $rows,
        $kolom,
        ['k1' => ['p:1' => '40']],
        ['UTS'],
    );

    expect($hasil['nilai']['k1']['p:1'])->toBe('40')
        ->and($hasil['errors'])->toHaveCount(1)
        ->and($hasil['errors'][0])->toContain('UTS');
});

it('Salin matriks memuat satu kolom per Sub-CPMK untuk asesmen dirinci', function () {
    $halaman = halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);
    $teks = (fn (): string => $this->teksSalinMatriks())->call($halaman->instance());

    expect(explode("\n", $teks)[0])->toBe("NIM\tNama\tAsesmen01/SUB-A\tAsesmen01/SUB-B");
});

it('kelas tervalidasi: menyimpan skor Sub-CPMK yang berubah menggugurkan validasi', function () {
    $halaman = halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);
    $validator = User::factory()->create();
    app(ValidasiNilaiService::class)->validasi($this->kelas, $validator, $halaman->call('loadMatrix')->get('sidikLaporan'));

    $halaman = halamanRinci($this->kelas)->assertSet('statusValidasi', 'tervalidasi');
    $halaman
        ->set("nilaiRinci.{$this->kmm1->id}.{$this->data['pivot2']->id}", '33')
        ->call('save');

    halamanRinci($this->kelas)->assertSet('statusValidasi', 'perlu_ulang');
});

it('dengan rincian menyala, sidik halaman setelah round-trip Livewire sama dengan sidik data dimuat', function () {
    app(RincianNilaiService::class)->nyalakan($this->kelas, $this->data['komponen'], $this->dosen);
    $layanan = app(DataLaporanKelasService::class);

    $halaman = halamanRinci($this->kelas)->call('loadMatrix');

    expect($halaman->get('sidikLaporan'))->toBe($layanan->sidik($layanan->muat($this->kelas)));

    $validator = User::factory()->create();
    app(ValidasiNilaiService::class)->validasi($this->kelas, $validator, $halaman->get('sidikLaporan'));

    $baru = halamanRinci($this->kelas)->assertSet('statusValidasi', 'tervalidasi');
    $baru->call('loadMatrix')->assertSet('statusValidasi', 'tervalidasi');

    expect($baru->instance()->laporanSesuaiValidasi())->toBeTrue();
});

it('menyalakan rincian tanpa mengubah angka tidak menggeser nilai asesmen maupun nilai akhir', function () {
    $layanan = app(DataLaporanKelasService::class);
    $sebelum = $layanan->muat($this->kelas)['nilai'];
    $akhirSebelum = $this->kelas->kelasMkMahasiswas()->orderBy('id')->pluck('nilai_angka', 'id')->all();

    halamanRinci($this->kelas)->call('rinciAsesmen', $this->komponenId);

    expect($layanan->muat($this->kelas)['nilai'])->toBe($sebelum)
        ->and($this->kelas->kelasMkMahasiswas()->orderBy('id')->pluck('nilai_angka', 'id')->all())->toBe($akhirSebelum);
});
