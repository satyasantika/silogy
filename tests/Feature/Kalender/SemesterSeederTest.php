<?php

use App\Modules\Kalender\Models\Semester;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mengisi 8 semester dari 20241 sampai 20272', function () {
    $this->seed(SemesterSeeder::class);

    expect(Semester::query()->count())->toBe(8)
        ->and(Semester::query()->orderBy('kode')->pluck('kode')->all())->toBe([
            '20241', '20242', '20251', '20252', '20261', '20262', '20271', '20272',
        ]);
});

it('hanya memiliki satu semester status_aktif setelah seed', function () {
    $this->seed(SemesterSeeder::class);

    expect(Semester::where('status_aktif', 1)->count())->toBe(1);
});
