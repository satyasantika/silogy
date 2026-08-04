<?php

use App\Models\User;

it('namaDenganGelar menggabungkan prefix, full_name, dan suffix', function () {
    $user = new User([
        'prefix' => 'Dr.',
        'full_name' => 'Satya Santika',
        'suffix' => 'M.Kom.',
    ]);

    expect($user->namaDenganGelar())->toBe('Dr. Satya Santika, M.Kom.');
});

it('namaDenganGelar hanya full_name bila gelar kosong', function () {
    $user = new User([
        'prefix' => null,
        'full_name' => 'Satya Santika',
        'suffix' => '',
    ]);

    expect($user->namaDenganGelar())->toBe('Satya Santika');
});

it('namaDenganGelar menampilkan gelar depan saja bila ada', function () {
    $user = new User([
        'prefix' => 'Prof. Dr.',
        'full_name' => 'Satya Santika',
        'suffix' => null,
    ]);

    expect($user->namaDenganGelar())->toBe('Prof. Dr. Satya Santika');
});

it('namaDenganGelar menampilkan gelar belakang saja bila ada', function () {
    $user = new User([
        'prefix' => '  ',
        'full_name' => 'Satya Santika',
        'suffix' => 'Ph.D.',
    ]);

    expect($user->namaDenganGelar())->toBe('Satya Santika, Ph.D.');
});
