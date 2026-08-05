<?php

use App\Modules\Penilaian\Support\NormalisasiBobotDesimal;

it('default desimal adalah satuan', function () {
    expect(NormalisasiBobotDesimal::DEFAULT)->toBe(0)
        ->and(NormalisasiBobotDesimal::dariData([]))->toBe(0)
        ->and(NormalisasiBobotDesimal::dariData(['desimal' => 2]))->toBe(2)
        ->and(NormalisasiBobotDesimal::dariData(['desimal' => 99]))->toBe(2);
});

it('field modal punya opsi 0–2 dengan default satuan', function () {
    $field = NormalisasiBobotDesimal::field();

    expect($field->getName())->toBe('desimal')
        ->and($field->getDefaultState())->toBe(0)
        ->and(NormalisasiBobotDesimal::options())->toHaveKeys([0, 1, 2]);
});
