<?php

namespace App\Modules\MK\Models;

use Database\Factories\CpmkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @use HasFactory<CpmkFactory>
 */
#[UseFactory(CpmkFactory::class)]
class Cpmk extends Model
{
    /** @use HasFactory<CpmkFactory> */
    use HasFactory, HasUuids;

    protected $table = 'cpmk';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Mk, $this>
     */
    public function mk(): BelongsTo
    {
        return $this->belongsTo(Mk::class);
    }

    /**
     * @return HasMany<MkCpmk, $this>
     */
    public function mkCpmks(): HasMany
    {
        return $this->hasMany(MkCpmk::class);
    }

    /**
     * @return HasManyThrough<Subcpmk, MkCpmk, $this>
     */
    public function subcpmks(): HasManyThrough
    {
        return $this->hasManyThrough(Subcpmk::class, MkCpmk::class);
    }
}
