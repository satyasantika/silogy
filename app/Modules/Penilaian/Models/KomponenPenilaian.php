<?php

namespace App\Modules\Penilaian\Models;

use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Models\Mk;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KomponenPenilaian extends Model
{
    use HasUuids;

    protected $table = 'komponen_penilaian';

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
     * @return BelongsTo<Mk, $this>
     */
    public function mk(): BelongsTo
    {
        return $this->belongsTo(Mk::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return BelongsTo<Evaluasi, $this>
     */
    public function evaluasi(): BelongsTo
    {
        return $this->belongsTo(Evaluasi::class);
    }

    /**
     * @return HasMany<SubcpmkKomponenPenilaian, $this>
     */
    public function subcpmkKomponens(): HasMany
    {
        return $this->hasMany(SubcpmkKomponenPenilaian::class, 'komponen_penilaian_id');
    }
}
