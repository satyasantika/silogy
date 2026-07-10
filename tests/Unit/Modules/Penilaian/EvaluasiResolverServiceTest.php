<?php

use App\Modules\Penilaian\Services\EvaluasiResolverService;
use Database\Seeders\EvaluasiSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(EvaluasiSeeder::class);
});

it('mencari evaluasi berdasarkan kode', function () {
    $evaluasi = EvaluasiResolverService::cariDariKodeAtauNama('quiz');

    expect($evaluasi)->not->toBeNull()
        ->and($evaluasi?->kode)->toBe('quiz');
});

it('mencari evaluasi berdasarkan nama case insensitive', function () {
    $evaluasi = EvaluasiResolverService::cariDariKodeAtauNama('QUIZ');

    expect($evaluasi)->not->toBeNull()
        ->and($evaluasi?->nama)->toBe('Quiz');
});

it('validasi evaluasi menolak kode tidak ada', function () {
    $hasil = EvaluasiResolverService::validasi('TidakAda');

    expect($hasil['valid'])->toBeFalse()
        ->and($hasil['keterangan'])->toContain('tidak ditemukan');
});
