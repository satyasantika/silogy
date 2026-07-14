<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use App\Modules\Penilaian\Services\SubcpmkAsesmenClipboardService;
use Database\Seeders\AcademicUnitSeeder;
use Database\Seeders\EvaluasiSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(SemesterSeeder::class);
    $this->seed(EvaluasiSeeder::class);

    $prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
    $semester = Semester::query()->where('status_aktif', true)->firstOrFail();
    $mk = Mk::factory()->create(['academic_unit_id' => $prodi->id]);
    $cpl = Cpl::factory()->forAcademicUnit($prodi)->create();
    $bok = Bok::factory()->forAcademicUnit($prodi)->create();
    $cplBok = CplBok::query()->create(['cpl_id' => $cpl->id, 'bok_id' => $bok->id, 'bobot' => 100]);
    $cplMk = CplMk::query()->create(['cpl_bok_id' => $cplBok->id, 'mk_id' => $mk->id, 'bobot' => 100]);
    $cpmk = Cpmk::query()->create(['mk_id' => $mk->id, 'kode' => 'CPMK-01', 'deskripsi' => 'Uji']);
    $mkCpmk = MkCpmk::query()->create(['cpl_mk_id' => $cplMk->id, 'cpmk_id' => $cpmk->id, 'bobot' => 100]);

    $this->komponen = KomponenPenilaian::query()->create([
        'mk_id' => $mk->id,
        'semester_id' => $semester->id,
        'evaluasi_id' => Evaluasi::query()->where('kode', 'uts')->value('id'),
        'kode' => 'UTS',
        'nama' => 'UTS',
        'bobot' => 10,
    ]);

    $this->sub1 = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id, 'semester_id' => $semester->id, 'kode' => 'SUB-01', 'deskripsi' => 'Sub 1',
    ]);
    $this->sub2 = Subcpmk::query()->create([
        'mk_cpmk_id' => $mkCpmk->id, 'semester_id' => $semester->id, 'kode' => 'SUB-02', 'deskripsi' => 'Sub 2',
    ]);

    $this->service = app(SubcpmkAsesmenClipboardService::class);
});

it('menolak sel tunggal yang melebihi bobot Asesmen', function () {
    $preview = $this->service->parsePaste(
        "Kode Asesmen\tSUB-01\nUTS\t11",
        collect([$this->komponen]),
        collect([$this->sub1, $this->sub2]),
        collect(),
    );

    expect($preview['ringkasan']['sel_invalid'])->toBe(1)
        ->and($preview['ringkasan']['sel_update'])->toBe(0)
        ->and($preview['baris'][0]['sel'][0]['status'])->toBe('invalid');
});

it('menolak seluruh baris bila total bobot antar sel melebihi bobot Asesmen, meski tiap sel sendiri valid', function () {
    $preview = $this->service->parsePaste(
        "Kode Asesmen\tSUB-01\tSUB-02\nUTS\t6\t8",
        collect([$this->komponen]),
        collect([$this->sub1, $this->sub2]),
        collect(),
    );

    expect($preview['ringkasan']['sel_update'])->toBe(0)
        ->and($preview['ringkasan']['sel_invalid'])->toBe(2)
        ->and($preview['errors'])->toContain(sprintf(
            'Baris 2: total bobot (14) melebihi bobot Asesmen "%s" (10) — seluruh sel pada baris ini tidak diterapkan.',
            $this->komponen->kode,
        ));

    $this->service->terapkan($preview);

    expect(SubcpmkKomponenPenilaian::query()->where('komponen_penilaian_id', $this->komponen->id)->count())->toBe(0);
});

it('memperhitungkan pivot lama pada sub-cpmk yang tidak ikut kolom tempelan saat menghitung total baris', function () {
    SubcpmkKomponenPenilaian::query()->create([
        'subcpmk_id' => $this->sub2->id,
        'komponen_penilaian_id' => $this->komponen->id,
        'bobot' => 7,
    ]);

    $currentBobots = collect([
        $this->komponen->id.'/'.$this->sub2->id => 7.0,
    ]);

    // Hanya SUB-01 yang ditempel (SUB-02 TIDAK ikut kolom) — sisa kapasitas
    // sesungguhnya cuma 3 (10 - 7 milik SUB-02 yang belum disentuh).
    $preview = $this->service->parsePaste(
        "Kode Asesmen\tSUB-01\nUTS\t4",
        collect([$this->komponen]),
        collect([$this->sub1, $this->sub2]),
        $currentBobots,
    );

    expect($preview['ringkasan']['sel_update'])->toBe(0)
        ->and($preview['ringkasan']['sel_invalid'])->toBe(1);
});

it('menerapkan sel yang valid ke database', function () {
    $preview = $this->service->parsePaste(
        "Kode Asesmen\tSUB-01\tSUB-02\nUTS\t6\t4",
        collect([$this->komponen]),
        collect([$this->sub1, $this->sub2]),
        collect(),
    );

    expect($preview['ringkasan']['sel_update'])->toBe(2);

    $hasil = $this->service->terapkan($preview);

    expect($hasil['diperbarui'])->toBe(2);

    $bobots = SubcpmkKomponenPenilaian::query()
        ->where('komponen_penilaian_id', $this->komponen->id)
        ->pluck('bobot', 'subcpmk_id');

    expect((float) $bobots[$this->sub1->id])->toBe(6.0)
        ->and((float) $bobots[$this->sub2->id])->toBe(4.0);
});
