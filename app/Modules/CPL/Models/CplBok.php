<?php

namespace App\Modules\CPL\Models;

use App\Modules\BoK\Models\Bok;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CplBok extends Model
{
    use HasUuids;

    protected $table = 'cpl_bok';

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
     * @return BelongsTo<Cpl, $this>
     */
    public function cpl(): BelongsTo
    {
        return $this->belongsTo(Cpl::class);
    }

    /**
     * @return BelongsTo<Bok, $this>
     */
    public function bok(): BelongsTo
    {
        return $this->belongsTo(Bok::class);
    }

    /**
     * @return HasMany<CplMk, $this>
     */
    public function cplMks(): HasMany
    {
        return $this->hasMany(CplMk::class);
    }
}
