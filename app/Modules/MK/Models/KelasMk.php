<?php

namespace App\Modules\MK\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KelasMk extends Model
{
    use HasUuids;

    protected $table = 'kelas_mk';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<MkUnit, $this>
     */
    public function mkUnit(): BelongsTo
    {
        return $this->belongsTo(MkUnit::class);
    }
}
