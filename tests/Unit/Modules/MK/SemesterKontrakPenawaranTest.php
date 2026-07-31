<?php

use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Support\SemesterKontrakPenawaran;
use Database\Seeders\SemesterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(SemesterSeeder::class);
    session()->forget(SemesterKontrakPenawaran::SESSION_KEY);
});

it('default semester kontrak penawaran mengikuti semester status aktif', function () {
    $aktif = Semester::query()->where('status_aktif', true)->firstOrFail();

    expect(SemesterKontrakPenawaran::defaultId())->toBe($aktif->id)
        ->and(SemesterKontrakPenawaran::currentId())->toBe($aktif->id);
});

it('set menyimpan semester valid ke session dan currentId mengikutinya', function () {
    $lain = Semester::query()->where('status_aktif', false)->orderByDesc('kode')->firstOrFail();

    SemesterKontrakPenawaran::set($lain->id);

    expect(session()->get(SemesterKontrakPenawaran::SESSION_KEY))->toBe($lain->id)
        ->and(SemesterKontrakPenawaran::currentId())->toBe($lain->id);
});

it('set mengabaikan id semester yang tidak ada di opsi', function () {
    $aktif = Semester::query()->where('status_aktif', true)->firstOrFail();

    SemesterKontrakPenawaran::set('00000000-0000-0000-0000-000000000000');

    expect(session()->get(SemesterKontrakPenawaran::SESSION_KEY))->toBeNull()
        ->and(SemesterKontrakPenawaran::currentId())->toBe($aktif->id);
});
