<?php

use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Support\KurikulumTerpilih;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;

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
