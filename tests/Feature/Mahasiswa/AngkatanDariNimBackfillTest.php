<?php

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\Mahasiswa\Support\AngkatanDariNim;
use Database\Seeders\AcademicUnitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(AcademicUnitSeeder::class);
    $this->prodi = AcademicUnit::query()->where('type', 'study_program')->firstOrFail();
});

it('isiBilaKosong mengisi angkatan dari NIM dan tidak menimpa yang sudah ada', function () {
    $kosong = Mahasiswa::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nim' => '232151001',
        'angkatan' => null,
    ]);

    expect(AngkatanDariNim::isiBilaKosong($kosong))->toBeTrue()
        ->and($kosong->fresh()->angkatan)->toBe('2023');

    $sudah = Mahasiswa::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nim' => '242151002',
        'angkatan' => '2020',
    ]);

    expect(AngkatanDariNim::isiBilaKosong($sudah))->toBeFalse()
        ->and($sudah->fresh()->angkatan)->toBe('2020');
});

it('perintah mahasiswa:isi-angkatan-dari-nim mengisi baris angkatan kosong', function () {
    Mahasiswa::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nim' => '232151111',
        'angkatan' => null,
    ]);
    Mahasiswa::factory()->create([
        'academic_unit_id' => $this->prodi->id,
        'nim' => '242151222',
        'angkatan' => '2024',
    ]);

    Artisan::call('mahasiswa:isi-angkatan-dari-nim');

    expect(Mahasiswa::query()->where('nim', '232151111')->value('angkatan'))->toBe('2023')
        ->and(Mahasiswa::query()->where('nim', '242151222')->value('angkatan'))->toBe('2024')
        ->and(Artisan::output())->toContain('Angkatan diisi: 1');
});
