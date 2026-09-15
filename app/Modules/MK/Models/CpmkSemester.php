<?php

namespace App\Modules\MK\Models;

use App\Modules\Kalender\Models\Semester;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lampiran CPMK ke satu semester. Baris cpmk yang sama boleh terlampir di
 * banyak semester — itulah cara "pakai CPMK semester lalu" memakai ulang ID.
 */
class CpmkSemester extends Model
{
    use HasUuids;

    protected $table = 'cpmk_semester';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Cpmk, $this>
     */
    public function cpmk(): BelongsTo
    {
        return $this->belongsTo(Cpmk::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
