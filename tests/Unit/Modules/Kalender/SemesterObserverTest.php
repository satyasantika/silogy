<?php

use App\Modules\Kalender\Models\Semester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('hanya memperbolehkan satu semester status_aktif', function () {
    $semesterA = Semester::query()->create([
        'kode' => '20241',
        'nama' => 'Ganjil 2024/2025',
        'tahun_mulai' => 2024,
        'tahun_selesai' => 2025,
        'jenis' => 'ganjil',
        'status_aktif' => true,
    ]);

    $semesterB = Semester::query()->create([
        'kode' => '20242',
        'nama' => 'Genap 2024/2025',
        'tahun_mulai' => 2024,
        'tahun_selesai' => 2025,
        'jenis' => 'genap',
        'status_aktif' => false,
    ]);

    $semesterB->update(['status_aktif' => true]);

    expect(Semester::where('status_aktif', true)->count())->toBe(1)
        ->and($semesterA->fresh()->status_aktif)->toBeFalse()
        ->and($semesterB->fresh()->status_aktif)->toBeTrue();
});
