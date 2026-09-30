<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Cpmk;
use App\Modules\MK\Models\CpmkSemester;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use App\Modules\MK\Models\MkUnit;
use App\Modules\MK\Models\Subcpmk;
use App\Modules\MK\Models\SubcpmkSemester;
use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;

/*
 * Helper test lintas berkas. Dimuat sekali lewat tests/Pest.php.
 *
 * Jangan mendefinisikan helper bersama di dalam berkas test: berkas test hanya
 * dimuat saat suite-nya dijalankan, sehingga berkas lain yang memakainya akan
 * fatal "Call to undefined function" ketika dijalankan sendiri atau paralel
 * (persis yang terjadi pada `php artisan test --parallel` di CI).
 */

/**
 * Siapkan skenario adaptasi: MK/CPL/BoK milik unit universitas yang diadaptasi
 * oleh kurikulum prodi aktif.
 *
 * @return array{mk: Mk, cpl: Cpl, bok: Bok}
 */
function siapkanAdaptasiCplBokUniv(object $context): array
{
    $kurikulumProdi = Kurikulum::query()->create([
        'academic_unit_id' => $context->prodi->id,
        'nama' => 'Kurikulum Uji Adaptasi CPL/BoK',
        'tahun' => 2026,
        'is_active' => true,
    ]);
    KurikulumTerpilih::set($kurikulumProdi->id);

    $mkUniv = Mk::factory()->forAcademicUnit($context->univ)->create();
    $cplUniv = Cpl::factory()->forAcademicUnit($context->univ)->create();
    $bokUniv = Bok::factory()->forAcademicUnit($context->univ)->create();
    $cplBokUniv = CplBok::query()->create(['cpl_id' => $cplUniv->id, 'bok_id' => $bokUniv->id]);
    CplMk::query()->create(['cpl_bok_id' => $cplBokUniv->id, 'mk_id' => $mkUniv->id, 'bobot' => 60]);

    MkUnit::factory()->forAcademicUnit($context->prodi)->forMk($mkUniv)->create(['is_active' => true]);

    return ['mk' => $mkUniv, 'cpl' => $cplUniv, 'bok' => $bokUniv];
}

/**
 * Buat (atau pakai ulang) satu CPMK lalu berlakukan pada sebuah semester.
 *
 * Sejak ikatan semester pindah ke tabel pivot, membuat baris saja tidak cukup:
 * tanpa lampiran semester, CPMK tidak akan muncul di daftar mana pun. Kode
 * CPMK unik per MK, jadi memanggil ulang dengan kode yang sama pada semester
 * berbeda sengaja MEMAKAI ULANG baris yang sama — itulah perilaku yang diuji.
 *
 * @param  array<string, mixed>  $attributes
 */
function cpmkUntukSemester(Mk|string $mk, Semester|string $semester, array $attributes = []): Cpmk
{
    $kode = $attributes['kode'] ?? 'CPMK01';
    unset($attributes['kode']);

    $cpmk = Cpmk::query()->firstOrCreate(
        [
            'mk_id' => $mk instanceof Mk ? $mk->id : $mk,
            'kode' => $kode,
        ],
        ['deskripsi' => 'Deskripsi CPMK.', ...$attributes],
    );

    CpmkSemester::query()->firstOrCreate([
        'cpmk_id' => $cpmk->id,
        'semester_id' => $semester instanceof Semester ? $semester->id : $semester,
    ]);

    return $cpmk;
}

/**
 * Buat (atau pakai ulang) satu Sub-CPMK lalu berlakukan pada sebuah semester.
 *
 * @param  array<string, mixed>  $attributes
 */
function subcpmkUntukSemester(MkCpmk|string $mkCpmk, Semester|string $semester, array $attributes = []): Subcpmk
{
    $kode = $attributes['kode'] ?? 'SUB01';
    // bobot milik lampiran semester, bukan baris Sub-CPMK.
    $bobot = $attributes['bobot'] ?? null;
    unset($attributes['kode'], $attributes['bobot']);

    $subcpmk = Subcpmk::query()->firstOrCreate(
        [
            'mk_cpmk_id' => $mkCpmk instanceof MkCpmk ? $mkCpmk->id : $mkCpmk,
            'kode' => $kode,
        ],
        ['deskripsi' => 'Deskripsi Sub-CPMK.', ...$attributes],
    );

    SubcpmkSemester::query()->updateOrCreate(
        [
            'subcpmk_id' => $subcpmk->id,
            'semester_id' => $semester instanceof Semester ? $semester->id : $semester,
        ],
        ['bobot' => $bobot],
    );

    return $subcpmk;
}

/**
 * Buat (atau pakai ulang) satu Asesmen lalu berlakukan pada sebuah semester
 * dengan bobotnya. Bobot memang milik pasangan (asesmen, semester).
 *
 * @param  array<string, mixed>  $attributes
 */
function komponenUntukSemester(Mk|string $mk, Semester|string $semester, array $attributes = []): KomponenPenilaian
{
    $kode = $attributes['kode'] ?? 'UTS';
    $bobot = $attributes['bobot'] ?? 100;
    unset($attributes['kode'], $attributes['bobot']);

    $komponen = KomponenPenilaian::query()->firstOrCreate(
        [
            'mk_id' => $mk instanceof Mk ? $mk->id : $mk,
            'kode' => $kode,
        ],
        [
            'evaluasi_id' => Evaluasi::query()->firstOrFail()->id,
            'nama' => 'UTS',
            ...$attributes,
        ],
    );

    KomponenPenilaianSemester::query()->updateOrCreate(
        [
            'komponen_penilaian_id' => $komponen->id,
            'semester_id' => $semester instanceof Semester ? $semester->id : $semester,
        ],
        ['bobot' => $bobot],
    );

    return $komponen;
}

/**
 * Semester tempat sebuah Asesmen berlaku. Dipakai fixture test untuk mengisi
 * subcpmk_komponenpenilaian.semester_id, yang kini NOT NULL: satu Asesmen
 * boleh berlaku di beberapa semester, jadi pemetaannya harus menyebut yang
 * mana. Fixture test umumnya hanya memberlakukan satu, jadi diambil yang ada.
 */
function semesterAsesmen(KomponenPenilaian|string $komponen): ?string
{
    $komponenId = $komponen instanceof KomponenPenilaian ? $komponen->id : $komponen;

    $semesterId = KomponenPenilaianSemester::query()
        ->where('komponen_penilaian_id', $komponenId)
        ->value('semester_id');

    return $semesterId === null ? null : (string) $semesterId;
}
