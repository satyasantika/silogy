<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Services\KonsolidasiLintasSemesterService;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\NilaiMahasiswa;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

/**
 * Konsolidasi berjalan SEKALI, pada skema lama, dari dalam migration
 * 2026_09_15_000002 — sesudahnya kolom semesternya sudah tidak ada. Supaya
 * algoritmanya benar-benar teruji (ini jalur dengan risiko kehilangan nilai
 * mahasiswa), skema lama dibangun ulang di sini: UQ baru dilepas dan kolom
 * semester dikembalikan, persis keadaan sebelum migration dijalankan.
 */
function pulihkanSkemaSebelumPivot(): void
{
    Schema::table('subcpmk', function ($table): void {
        $table->dropUnique('uq_subcpmk_mkcpmk_kode');
        $table->uuid('semester_id')->nullable();
        $table->double('bobot')->nullable();
    });

    Schema::table('komponen_penilaian', function ($table): void {
        $table->dropUnique('uq_komponen_mk_kode');
        $table->uuid('semester_id')->nullable();
        $table->decimal('bobot', 5, 2)->default(100);
    });

    Schema::table('cpmk', function ($table): void {
        $table->dropUnique('uq_cpmk_mk_kode');
    });

    DB::table('cpmk_semester')->delete();
    DB::table('subcpmk_semester')->delete();
    DB::table('komponen_penilaian_semester')->delete();
}

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $this->semesterA = Semester::query()->where('status_aktif', true)->firstOrFail();
    $this->semesterB = Semester::query()->firstOrCreate(
        ['kode' => '29992'],
        [
            'nama' => 'Genap Uji Konsolidasi',
            'jenis' => 'genap',
            'tahun_mulai' => 2999,
            'tahun_selesai' => 3000,
            'status_aktif' => false,
        ],
    );

    $this->kurikulum = Kurikulum::query()->create([
        'academic_unit_id' => $this->prodi->id,
        'nama' => 'Kurikulum Uji Konsolidasi',
        'kode' => 'KNSLD',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    $this->mk = Mk::factory()->forKurikulum($this->kurikulum)->create();
    $mkUnit = MkUnit::factory()->forMk($this->mk)->forAcademicUnit($this->prodi)->create();

    $cpl = Cpl::factory()->forKurikulum($this->kurikulum)->create();
    $bok = Bok::factory()->forKurikulum($this->kurikulum)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $this->mk->id, 'bobot' => 100]);
    $cpmk = cpmkUntukSemester($this->mk, $this->semesterA, ['kode' => 'CPMK01']);
    $this->mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);

    pulihkanSkemaSebelumPivot();

    // Dua kelas berbeda semester, masing-masing dengan satu mahasiswa.
    $this->kelasA = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id, 'semester_id' => $this->semesterA->id, 'kode_kelas' => 'A',
    ]);
    $this->kelasB = KelasMk::query()->create([
        'mk_unit_id' => $mkUnit->id, 'semester_id' => $this->semesterB->id, 'kode_kelas' => 'A',
    ]);
    $this->kmmA = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $this->kelasA->id,
        'mahasiswa_id' => Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id])->id,
    ]);
    $this->kmmB = KelasMkMahasiswa::query()->create([
        'kelas_mk_id' => $this->kelasB->id,
        'mahasiswa_id' => Mahasiswa::factory()->create(['academic_unit_id' => $this->prodi->id])->id,
    ]);

    $evaluasi = Evaluasi::query()->where('kode', 'uts')->firstOrFail();

    // Inilah bentuk data lama: Sub-CPMK & Asesmen KEMBAR antar semester,
    // hasil mekanisme salin-antar-semester yang dulu membuat ID baru.
    // created_at dibedakan eksplisit: pemenang peleburan ditentukan oleh
    // baris TERTUA, dan aturan itulah yang diuji — bukan kebetulan urutan.
    $this->kembar = collect([$this->semesterA, $this->semesterB])->values()->map(
        fn (Semester $semester, int $i): array => [
            'semester' => $semester,
            'subcpmk' => tap(new Subcpmk, function (Subcpmk $s) use ($semester, $i): void {
                $s->forceFill([
                    'id' => (string) Str::uuid(),
                    'mk_cpmk_id' => $this->mkCpmk->id,
                    'semester_id' => $semester->id,
                    'kode' => 'SUB01',
                    'deskripsi' => 'Deskripsi Sub-CPMK.',
                    'created_at' => now()->subDays(10 - $i),
                    'updated_at' => now()->subDays(10 - $i),
                ])->save();
            }),
            'komponen' => tap(new KomponenPenilaian, function (KomponenPenilaian $k) use ($semester, $evaluasi, $i): void {
                $k->forceFill([
                    'id' => (string) Str::uuid(),
                    'mk_id' => $this->mk->id,
                    'semester_id' => $semester->id,
                    'evaluasi_id' => $evaluasi->id,
                    'kode' => 'UTS',
                    'nama' => 'UTS',
                    'bobot' => 100,
                    'created_at' => now()->subDays(10 - $i),
                    'updated_at' => now()->subDays(10 - $i),
                ])->save();
            }),
        ],
    );

    foreach ($this->kembar as $i => $pasangan) {
        $pivot = SubcpmkKomponenPenilaian::query()->create([
            'subcpmk_id' => $pasangan['subcpmk']->id,
            'komponen_penilaian_id' => $pasangan['komponen']->id,
            'semester_id' => $pasangan['semester']->id,
            'bobot' => 100,
        ]);

        NilaiMahasiswa::query()->create([
            'subcpmk_komponenpenilaian_id' => $pivot->id,
            'kelas_mk_mahasiswa_id' => $i === 0 ? $this->kmmA->id : $this->kmmB->id,
            'nilai' => $i === 0 ? 80 : 90,
        ]);
    }
});

it('melaporkan kandidat peleburan tanpa mengubah apa pun (dry-run)', function () {
    $sebelum = [
        'subcpmk' => Subcpmk::query()->count(),
        'komponen' => KomponenPenilaian::query()->count(),
        'nilai' => NilaiMahasiswa::query()->count(),
    ];

    $rencana = app(KonsolidasiLintasSemesterService::class)->rencana();

    expect($rencana->grupUntuk('subcpmk'))->toHaveCount(1)
        ->and($rencana->grupUntuk('komponen_penilaian'))->toHaveCount(1)
        ->and($rencana->grupUntuk('subcpmk')[0]->semesterIds)->toHaveCount(2)
        ->and($rencana->nilaiTerbuang)->toBeEmpty();

    expect(Subcpmk::query()->count())->toBe($sebelum['subcpmk'])
        ->and(KomponenPenilaian::query()->count())->toBe($sebelum['komponen'])
        ->and(NilaiMahasiswa::query()->count())->toBe($sebelum['nilai']);
});

it('melebur baris kembar jadi satu ID tanpa kehilangan nilai mahasiswa', function () {
    $nilaiSebelum = NilaiMahasiswa::query()->count();

    app(KonsolidasiLintasSemesterService::class)->terapkan();

    // Tersisa satu baris, terlampir di KEDUA semester.
    expect(Subcpmk::query()->count())->toBe(1)
        ->and(KomponenPenilaian::query()->count())->toBe(1);

    $subcpmkId = Subcpmk::query()->value('id');

    expect(DB::table('subcpmk_semester')->where('subcpmk_id', $subcpmkId)->count())->toBe(2);

    // Pemenangnya adalah baris tertua — ID-nya bertahan, bukan ID baru.
    expect($subcpmkId)->toBe($this->kembar[0]['subcpmk']->id);

    // Yang paling penting: tidak ada nilai mahasiswa yang hilang.
    expect(NilaiMahasiswa::query()->count())->toBe($nilaiSebelum);
});

it('idempoten: menjalankan ulang tidak menemukan kandidat lagi', function () {
    app(KonsolidasiLintasSemesterService::class)->terapkan();

    $rencana = app(KonsolidasiLintasSemesterService::class)->rencana();

    expect($rencana->kosong())->toBeTrue();
});
