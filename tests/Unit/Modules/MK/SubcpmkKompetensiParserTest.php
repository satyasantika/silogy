<?php

use App\Modules\MK\Services\SubcpmkKompetensiParser;

it('parse kompetensi bloom dari format c3,a2,p2', function () {
    $hasil = SubcpmkKompetensiParser::parse('C3,A2,P2');

    expect($hasil)->toBe([
        'bloom_kognitif' => 'C3',
        'bloom_afektif' => 'A2',
        'bloom_psikomotorik' => 'P2',
    ]);
});

it('parse kompetensi kosong mengembalikan null', function () {
    expect(SubcpmkKompetensiParser::parse(''))->toBe([
        'bloom_kognitif' => null,
        'bloom_afektif' => null,
        'bloom_psikomotorik' => null,
    ]);
});

it('validasi kompetensi menolak format salah', function () {
    $hasil = SubcpmkKompetensiParser::validasi('C3,X9');

    expect($hasil['valid'])->toBeFalse()
        ->and($hasil['keterangan'])->toContain('X9');
});
