<?php

namespace App\Modules\Kurikulum\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProfilLulusan extends Model
{
    use HasUuids;

    protected $table = 'profil_lulusan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Kurikulum, $this>
     */
    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class);
    }

    /**
     * @return HasMany<ProfilIndikator, $this>
     */
    public function indikators(): HasMany
    {
        return $this->hasMany(ProfilIndikator::class, 'profil_id');
    }
}
