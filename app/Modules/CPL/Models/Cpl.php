<?php

namespace App\Modules\CPL\Models;

use App\Modules\Institusi\Models\AcademicUnit;
use Database\Factories\CplFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<CplFactory>
 */
#[UseFactory(CplFactory::class)]
class Cpl extends Model
{
    /** @use HasFactory<CplFactory> */
    use HasFactory, HasUuids;

    protected $table = 'cpl';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * @return HasMany<CplBok, $this>
     */
    public function cplBoks(): HasMany
    {
        return $this->hasMany(CplBok::class);
    }

    /**
     * @return HasMany<CplProfilLulusan, $this>
     */
    public function cplProfilLulusan(): HasMany
    {
        return $this->hasMany(CplProfilLulusan::class);
    }
}
