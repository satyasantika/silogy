<?php

namespace App\Modules\MK\Models;

use App\Modules\CPL\Models\CplMk;
use Database\Factories\MkCpmkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<MkCpmkFactory>
 */
#[UseFactory(MkCpmkFactory::class)]
class MkCpmk extends Model
{
    /** @use HasFactory<MkCpmkFactory> */
    use HasFactory, HasUuids;

    protected $table = 'mk_cpmk';

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
     * @return BelongsTo<CplMk, $this>
     */
    public function cplMk(): BelongsTo
    {
        return $this->belongsTo(CplMk::class);
    }

    /**
     * @return BelongsTo<Cpmk, $this>
     */
    public function cpmk(): BelongsTo
    {
        return $this->belongsTo(Cpmk::class);
    }

    /**
     * @return HasMany<Subcpmk, $this>
     */
    public function subcpmks(): HasMany
    {
        return $this->hasMany(Subcpmk::class);
    }
}
