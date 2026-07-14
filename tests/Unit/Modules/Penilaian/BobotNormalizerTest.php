<?php

use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Collection;

it('keSeratus membagi proporsional dan membulatkan agar total tepat 100', function () {
    $hasil = BobotNormalizer::keSeratus(new Collection(['a' => 40.0, 'b' => 60.0]));

    expect($hasil)->toBe(['a' => 40.0, 'b' => 60.0])
        ->and(array_sum($hasil))->toBe(100.0);
});

it('keSeratus mengoreksi sisa pembulatan pada kunci terbesar', function () {
    $hasil = BobotNormalizer::keSeratus(new Collection(['a' => 1.0, 'b' => 1.0, 'c' => 1.0]));

    expect(array_sum($hasil))->toBe(100.0)
        ->and($hasil)->toHaveCount(3);
});

it('keTarget membagi proporsional terhadap target desimal tanpa dipaksa bilangan bulat', function () {
    $hasil = BobotNormalizer::keTarget(new Collection(['a' => 50.0, 'b' => 50.0]), 7.5);

    expect($hasil)->toBe(['a' => 3.75, 'b' => 3.75])
        ->and(array_sum($hasil))->toBe(7.5);
});

it('keTarget tetap presisi 2 desimal saat pembagian tidak bulat', function () {
    $hasil = BobotNormalizer::keTarget(new Collection(['a' => 1.0, 'b' => 1.0, 'c' => 1.0]), 10.0);

    expect(array_sum($hasil))->toBe(10.0)
        ->and($hasil)->toHaveCount(3);
});

it('keTarget mengembalikan array kosong bila target nol atau negatif', function () {
    expect(BobotNormalizer::keTarget(new Collection(['a' => 10.0]), 0.0))->toBe([])
        ->and(BobotNormalizer::keTarget(new Collection(['a' => 10.0]), -5.0))->toBe([]);
});

it('keTarget mengembalikan array kosong bila total bobot nol atau koleksi kosong', function () {
    expect(BobotNormalizer::keTarget(new Collection, 100.0))->toBe([])
        ->and(BobotNormalizer::keTarget(new Collection(['a' => 0.0]), 100.0))->toBe([]);
});
