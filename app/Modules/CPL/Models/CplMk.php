<?php

namespace App\Modules\CPL\Models;

use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CplMk extends Model
{
    use HasUuids;

    protected $table = 'cpl_mk';

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
     * @return BelongsTo<CplBok, $this>
     */
    public function cplBok(): BelongsTo
    {
        return $this->belongsTo(CplBok::class);
    }

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
