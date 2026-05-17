<?php

namespace App\Modules\MK\Models;

use App\Modules\Institusi\Models\AcademicUnit;
use Database\Factories\MkUnitFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<MkUnitFactory>
 */
#[UseFactory(MkUnitFactory::class)]
class MkUnit extends Model
{
    /** @use HasFactory<MkUnitFactory> */
    use HasFactory, HasUuids;

    protected $table = 'mk_units';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'semester_ke' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Mk, $this>
     */
    public function mk(): BelongsTo
    {
        return $this->belongsTo(Mk::class);
    }

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * @return HasMany<KelasMk, $this>
     */
    public function kelasMks(): HasMany
    {
        return $this->hasMany(KelasMk::class);
    }
}
