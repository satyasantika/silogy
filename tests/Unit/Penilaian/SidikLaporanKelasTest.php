<?php

use App\Modules\Penilaian\Services\SidikLaporanKelas;

it('sidik tidak bergantung pada urutan kunci maupun tipe angka', function () {
    $s = new SidikLaporanKelas;
    $a = ['rows' => [['nim' => '1', 'nilai_angka' => 80.0]], 'targetCapaianLulusan' => 75];
    $b = ['targetCapaianLulusan' => '75', 'rows' => [['nilai_angka' => '80.00', 'nim' => '1']]];
    expect($s->dariData($a))->toBe($s->dariData($b))->toHaveLength(64);
});

it('sidik berubah bila nilai atau urutan baris berubah', function () {
    $s = new SidikLaporanKelas;
    $dasar = ['rows' => [['nim' => '1', 'nilai_angka' => 80.0], ['nim' => '2', 'nilai_angka' => 60.0]]];
    expect($s->dariData($dasar))->not->toBe($s->dariData(['rows' => [['nim' => '1', 'nilai_angka' => 81.0], ['nim' => '2', 'nilai_angka' => 60.0]]]));
    expect($s->dariData($dasar))->not->toBe($s->dariData(['rows' => array_reverse($dasar['rows'])]));
});

it('membedakan null dari nol', function () {
    $s = new SidikLaporanKelas;
    expect($s->dariData(['x' => null]))->not->toBe($s->dariData(['x' => 0]));
});

it('membedakan boolean dari string dan angka', function () {
    $s = new SidikLaporanKelas;
    expect($s->dariData(['x' => true]))->not->toBe($s->dariData(['x' => 1]));
    expect($s->dariData(['x' => false]))->not->toBe($s->dariData(['x' => 0]));
});
