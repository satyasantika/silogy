<?php

use App\Modules\Mahasiswa\Support\AngkatanDariNim;

it('menurunkan angkatan 20XX dari dua digit pertama NIM', function (string $nim, string $expected) {
    expect(AngkatanDariNim::dari($nim))->toBe($expected);
})->with([
    ['232151001', '2023'],
    ['242151111004', '2024'],
    ['259255111003', '2025'],
    [' 232151001 ', '2023'],
]);

it('mengembalikan null bila NIM tidak cukup digit awalan', function (string $nim) {
    expect(AngkatanDariNim::dari($nim))->toBeNull();
})->with([
    [''],
    ['1'],
    ['AB2151001'],
    ['2X2151001'],
]);

it('label memakai tahun atau bucket Tanpa angkatan', function () {
    expect(AngkatanDariNim::label('2023'))->toBe('2023')
        ->and(AngkatanDariNim::label(null))->toBe(AngkatanDariNim::LABEL_TANPA_ANGKATAN)
        ->and(AngkatanDariNim::label(''))->toBe(AngkatanDariNim::LABEL_TANPA_ANGKATAN);
});
