<?php

use App\Modules\Penilaian\Filament\Pages\Concerns\HasLaporanKelasMk;

$subject = new class
{
    use HasLaporanKelasMk;

    public function getMkTerpilihProperty(): mixed
    {
        return null;
    }

    public function getSemesterTerpilihProperty(): string
    {
        return '';
    }
};

it('warnaKetercapaian netral bila nilai null', function () use ($subject) {
    expect($subject->warnaKetercapaian(null, 75))->toBe(['class' => 'silogy-tone-neutral']);
});

it('warnaKetercapaian sukses bila nilai mencapai target', function () use ($subject) {
    expect($subject->warnaKetercapaian(75.0, 75))->toBe(['class' => 'silogy-tone-success'])
        ->and($subject->warnaKetercapaian(90.0, 75))->toBe(['class' => 'silogy-tone-success']);
});

it('warnaKetercapaian warning bila nilai di antara 75% target dan target', function () use ($subject) {
    // Target 80 → ambang warning = 60; 60..79.99 → warning
    expect($subject->warnaKetercapaian(60.0, 80))->toBe(['class' => 'silogy-tone-warning'])
        ->and($subject->warnaKetercapaian(79.9, 80))->toBe(['class' => 'silogy-tone-warning']);
});

it('warnaKetercapaian danger bila nilai di bawah 75% target', function () use ($subject) {
    expect($subject->warnaKetercapaian(59.9, 80))->toBe(['class' => 'silogy-tone-danger'])
        ->and($subject->warnaKetercapaian(0.0, 75))->toBe(['class' => 'silogy-tone-danger']);
});

it('warnaKetercapaian memakai fallback target 75 bila target null', function () use ($subject) {
    expect($subject->warnaKetercapaian(75.0, null))->toBe(['class' => 'silogy-tone-success'])
        ->and($subject->warnaKetercapaian(56.25, null))->toBe(['class' => 'silogy-tone-warning'])
        ->and($subject->warnaKetercapaian(56.0, null))->toBe(['class' => 'silogy-tone-danger']);
});
