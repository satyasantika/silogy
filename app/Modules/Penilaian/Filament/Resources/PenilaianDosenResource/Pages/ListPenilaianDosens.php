<?php

namespace App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource\Pages;

use App\Modules\Penilaian\Filament\Resources\PenilaianDosenResource;
use App\Modules\Penilaian\Support\PenilaianSemesterTerpilih;
use Filament\Resources\Pages\ListRecords;

class ListPenilaianDosens extends ListRecords
{
    protected static string $resource = PenilaianDosenResource::class;

    /**
     * Dukung tautan langsung (mis. dari widget dashboard) yang menyertakan
     * ?semester_id=... agar halaman ini langsung terfilter ke semester itu.
     */
    public function mount(): void
    {
        parent::mount();

        $semesterId = request()->query('semester_id');

        if (is_string($semesterId) && filled($semesterId)) {
            PenilaianSemesterTerpilih::set($semesterId);
        }
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
