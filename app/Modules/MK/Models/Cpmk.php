<?php

namespace App\Modules\MK\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cpmk extends Model
{
    use HasUuids;

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
}
