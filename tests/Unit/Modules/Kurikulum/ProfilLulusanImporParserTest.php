<?php

use App\Modules\Kurikulum\Support\ProfilLulusanImporParser;
use Tests\TestCase;

uses(TestCase::class);

it('mem-parse indikator bernomor dan menghitung jumlahnya', function () {
    $raw = '(1) Menguasai konsep teoritis tentang konsep-konsep dasar matematika '
        .'(2) Menguasai dan mengaplikasikan strategi dan metode pembelajaran dasar yang efektif untuk menyampaikan materi matematika '
        .'(3) Mampu memanfaatkan teknologi untuk mendukung proses dan evaluasi pembelajaran '
        .'(4) Mampu mendesain pengelolaan kelas yang baik untuk terciptanya lingkungan belajar yang kondusif';

    $items = ProfilLulusanImporParser::parseIndikators($raw);

    expect($items)->toHaveCount(4)
        ->and(ProfilLulusanImporParser::jumlahIndikator($raw))->toBe(4)
        ->and(ProfilLulusanImporParser::ringkasanIndikator($raw))->toBe('4 indikator terdeteksi')
        ->and(ProfilLulusanImporParser::validateIndikator($raw))->toBeNull()
        ->and($items[0])->toContain('konsep teoritis')
        ->and($items[3])->toContain('lingkungan belajar');
});

it('menolak penomoran indikator yang tidak berurutan', function () {
    expect(ProfilLulusanImporParser::validateIndikator('(1) Satu (3) Tiga'))
        ->toBe('Penomoran indikator harus berurutan mulai (1) tanpa nomor ganda atau loncat.');
});

it('menganggap kolom indikator kosong sebagai tanpa indikator', function () {
    expect(ProfilLulusanImporParser::ringkasanIndikator(''))->toBe('Tanpa indikator')
        ->and(ProfilLulusanImporParser::validateIndikator(''))->toBeNull();
});
