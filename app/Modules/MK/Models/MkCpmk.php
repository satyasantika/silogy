<?php

namespace App\Modules\MK\Models;

use App\Modules\CPL\Models\CplMk;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MkCpmk extends Model
{
    use HasUuids;

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
}
