<?php

namespace App\Modules\BoK\Models;

use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\Institusi\Models\AcademicUnit;
use Database\Factories\BokFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<BokFactory>
 */
#[UseFactory(BokFactory::class)]
class Bok extends Model
{
    /** @use HasFactory<BokFactory> */
    use HasFactory, HasUuids;

    protected $table = 'bok';

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
     * @return BelongsToMany<Cpl, $this>
     */
    public function cpls(): BelongsToMany
    {
        return $this->belongsToMany(
            Cpl::class,
            'cpl_bok',
            'bok_id',
            'cpl_id',
        )->withPivot(['id', 'bobot'])->withTimestamps();
    }

    /**
     * BoK belum dipetakan ke CPL manapun.
     */
    public function belumDiinteraksikan(): bool
    {
        return ! $this->cplBoks()->exists();
    }
}
