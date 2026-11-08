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
    $evaluasi = EvaluasiResolverService::cariDariKodeAtauNama('KUIS');

    expect($evaluasi)->not->toBeNull()
        ->and($evaluasi?->nama)->toBe('Kuis');
});

it('nama lama sebelum penyelarasan si-obe tetap dikenali saat impor', function (string $namaLama, string $kode) {
    expect(EvaluasiResolverService::cariDariKodeAtauNama($namaLama)?->kode)->toBe($kode);
})->with([
    ['Quiz', 'quiz'],
    ['ujian tengah semester', 'uts'],
    ['Ujian Akhir Semester', 'uas'],
    ['Proyek Individu', 'proyek_individu'],
    ['Proyek Kelompok', 'proyek_kelompok'],
    ['Partisipasi Individu', 'partisipasi_individu'],
    ['Partisipasi Kelompok', 'partisipasi_kelompok'],
    ['Studi Kasus', 'proyek_individu'],
    ['studi_kasus', 'proyek_individu'],
]);

it('validasi evaluasi menolak kode tidak ada', function () {
    $hasil = EvaluasiResolverService::validasi('TidakAda');

    expect($hasil['valid'])->toBeFalse()
        ->and($hasil['keterangan'])->toContain('tidak ditemukan');
});
