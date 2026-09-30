<?php

namespace App\Modules\MK\Models;

use App\Modules\Kalender\Models\Semester;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lampiran Sub-CPMK ke satu semester, beserta bobotnya pada semester itu.
 *
 * bobot di sini TURUNAN — dihitung ulang dari pemetaan Sub-CPMK ↔ Asesmen
 * pada semester yang bersangkutan (lihat SubcpmkAsesmenPemetaanService::
 * recalculateBobotSubcpmk()). Disimpan per semester karena satu baris
 * subcpmk kini bisa dipakai beberapa semester dengan asesmen berbeda.
 */
class SubcpmkSemester extends Model
{
    use HasUuids;

    protected $table = 'subcpmk_semester';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'bobot' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Subcpmk, $this>
     */
    public function subcpmk(): BelongsTo
    {
        return $this->belongsTo(Subcpmk::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
