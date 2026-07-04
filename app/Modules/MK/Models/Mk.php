<?php

namespace App\Modules\MK\Models;

use App\Models\User;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Support\Concerns\LogsSilogyActivity;
use Database\Factories\MkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @use HasFactory<MkFactory>
 *
 * @property-read int $total_sks
 */
#[UseFactory(MkFactory::class)]
class Mk extends Model
{
    /** @use HasFactory<MkFactory> */
    use HasFactory, HasUuids, LogsSilogyActivity;

    protected $table = 'mk';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sks' => 'integer',
            'sks_teori' => 'integer',
            'sks_praktik' => 'integer',
            'sks_lapangan' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return Attribute<int, never>
     */
    protected function totalSks(): Attribute
    {
        return Attribute::get(
            fn (): int => (int) $this->sks_teori + (int) $this->sks_praktik + (int) $this->sks_lapangan,
        );
    }

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * Koordinator MK ditetapkan tim kurikulum pemilik MK; menjadi default
     * koordinator saat kelas MK dibuat.
     *
     * @return BelongsTo<User, $this>
     */
    public function koordinatorMk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'koordinator_mk_id');
    }

    /**
     * @return HasMany<MkUnit, $this>
     */
    public function mkUnits(): HasMany
    {
        return $this->hasMany(MkUnit::class);
    }

    /**
     * @return HasMany<Cpmk, $this>
     */
    public function cpmks(): HasMany
    {
        return $this->hasMany(Cpmk::class);
    }

    /**
     * @return HasManyThrough<MkCpmk, Cpmk, $this>
     */
    public function mkCpmks(): HasManyThrough
    {
        return $this->hasManyThrough(MkCpmk::class, Cpmk::class);
    }

    /**
     * @return HasMany<CplMk, $this>
     */
    public function cplMks(): HasMany
    {
        return $this->hasMany(CplMk::class);
    }
}
