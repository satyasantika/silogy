<?php

namespace App\Modules\Penilaian\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluasi extends Model
{
    use HasUuids;

    protected $table = 'evaluasi';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return HasMany<KomponenPenilaian, $this>
     */
    public function komponenPenilaians(): HasMany
    {
        return $this->hasMany(KomponenPenilaian::class);
    }
}
