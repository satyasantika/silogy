<?php

namespace App\Modules\Kalender\Observers;

use App\Modules\Kalender\Models\Semester;

class SemesterObserver
{
    public function saving(Semester $semester): void
    {
        if (! $semester->status_aktif) {
            return;
        }

        Semester::query()
            ->when($semester->exists, fn ($query) => $query->where('id', '!=', $semester->id))
            ->update(['status_aktif' => false]);
    }
}
