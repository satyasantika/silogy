<?php

use App\Modules\Penilaian\Support\BobotNormalizer;
use Illuminate\Support\Collection;

it('keSeratus default membulatkan ke satuan agar total tepat 100', function () {
    $hasil = BobotNormalizer::keSeratus(new Collection(['a' => 1.0, 'b' => 1.0, 'c' => 1.0]));

    expect(array_sum($hasil))->toBe(100.0)
        ->and($hasil)->toHaveCount(3)
        ->and(collect($hasil)->every(fn (float $b): bool => abs($b - round($b)) < 1e-9))->toBeTrue();
});

it('keSeratus dengan 2 desimal mempertahankan perilaku lama', function () {
    $hasil = BobotNormalizer::keSeratus(new Collection(['a' => 40.0, 'b' => 60.0]), desimal: 2);

    expect($hasil)->toBe(['a' => 40.0, 'b' => 60.0])
        ->and(array_sum($hasil))->toBe(100.0);
});

it('keSeratus 2 desimal mengoreksi sisa pembulatan pada kunci terbesar', function () {
    $hasil = BobotNormalizer::keSeratus(new Collection(['a' => 1.0, 'b' => 1.0, 'c' => 1.0]), desimal: 2);

    expect(array_sum($hasil))->toBe(100.0)
        ->and($hasil)->toHaveCount(3);
});

it('keTarget default membulatkan target 7.5 ke satuan lalu membagi', function () {
    $hasil = BobotNormalizer::keTarget(new Collection(['a' => 50.0, 'b' => 50.0]), 7.5);

    expect(array_sum($hasil))->toBe(8.0)
        ->and($hasil)->toBe(['a' => 4.0, 'b' => 4.0]);
});

it('keTarget dengan 2 desimal mempertahankan target desimal', function () {
    $hasil = BobotNormalizer::keTarget(new Collection(['a' => 50.0, 'b' => 50.0]), 7.5, desimal: 2);

    expect($hasil)->toBe(['a' => 3.75, 'b' => 3.75])
        ->and(array_sum($hasil))->toBe(7.5);
});

it('keTarget 2 desimal tetap presisi saat pembagian tidak bulat', function () {
    $hasil = BobotNormalizer::keTarget(new Collection(['a' => 1.0, 'b' => 1.0, 'c' => 1.0]), 10.0, desimal: 2);

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

it('sudahSesuai false bila masih ada digit di luar N desimal meski total pas', function () {
    $bobot = new Collection(['a' => 33.33, 'b' => 33.33, 'c' => 33.34]);

    expect(BobotNormalizer::sudahSesuai($bobot, 100.0, desimal: 0))->toBeFalse()
        ->and(BobotNormalizer::sudahSesuai($bobot, 100.0, desimal: 2))->toBeTrue();
});
