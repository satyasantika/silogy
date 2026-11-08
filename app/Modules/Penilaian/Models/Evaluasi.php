<?php

namespace App\Modules\Penilaian\Models;

use Illuminate\Database\Eloquent\Builder;
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

    /**
     * Urutan tampil komponen SI-OBE (kolom urutan), lalu kode.
     *
     * @param  Builder<Evaluasi>  $query
     * @return Builder<Evaluasi>
     */
    public function scopeUrut(Builder $query): Builder
    {
        return $query->orderByRaw('urutan IS NULL')->orderBy('urutan')->orderBy('kode');
    }
}
