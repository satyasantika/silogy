<?php

namespace App\Modules\Penilaian\Models;

use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Models\Subcpmk;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubcpmkKomponenPenilaian extends Model
{
    use HasUuids;

    protected $table = 'subcpmk_komponenpenilaian';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'bobot' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Subcpmk, $this>
     */
    public function subcpmk(): BelongsTo
    {
        return $this->belongsTo(Subcpmk::class);
    }

    /**
     * @return BelongsTo<KomponenPenilaian, $this>
     */
    public function komponenPenilaian(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return HasMany<NilaiMahasiswa, $this>
     */
    public function nilaiMahasiswas(): HasMany
    {
        return $this->hasMany(NilaiMahasiswa::class, 'subcpmk_komponenpenilaian_id');
    }
}
