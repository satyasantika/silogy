<?php

namespace App\Modules\Penilaian\Models;

use App\Modules\Kalender\Models\Semester;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Berlakunya satu Asesmen (komponen penilaian) pada satu semester, beserta
 * bobotnya terhadap nilai akhir MK di semester itu. Bobot disimpan di sini
 * — bukan pada baris induk — karena asesmen yang sama boleh dipakai ulang
 * lintas semester dengan bobot berbeda.
 */
class KomponenPenilaianSemester extends Model
{
    use HasUuids;

    protected $table = 'komponen_penilaian_semester';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'bobot' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<KomponenPenilaian, $this>
     */
    public function komponenPenilaian(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
