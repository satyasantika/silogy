<?php

namespace App\Modules\Penilaian\Models;

use App\Modules\Kelas\Models\KelasMk;
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
     * @return BelongsTo<KelasMk, $this>
     */
    public function kelasMk(): BelongsTo
    {
        return $this->belongsTo(KelasMk::class);
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
