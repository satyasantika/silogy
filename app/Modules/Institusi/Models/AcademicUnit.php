<?php

namespace App\Modules\Institusi\Models;

use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Support\Concerns\LogsSilogyActivity;
use Database\Factories\AcademicUnitFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<AcademicUnitFactory>
 */
#[UseFactory(AcademicUnitFactory::class)]
class AcademicUnit extends Model
{
    /** @use HasFactory<AcademicUnitFactory> */
    use HasFactory, HasUuids, LogsSilogyActivity;

    protected $table = 'academic_units';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<AcademicUnit, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return HasMany<AcademicUnitUser, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(AcademicUnitUser::class);
    }

    /**
     * @return HasMany<Mahasiswa, $this>
     */
    public function mahasiswas(): HasMany
    {
        return $this->hasMany(Mahasiswa::class);
    }

    /**
     * @param  Builder<AcademicUnit>  $query
     * @return Builder<AcademicUnit>
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function isUniversity(): bool
    {
        return $this->type === 'university';
    }

    public function isFaculty(): bool
    {
        return $this->type === 'faculty';
    }

    public function isJurusan(): bool
    {
        return $this->type === 'department';
    }

    public function isProdi(): bool
    {
        return $this->type === 'study_program';
    }
}
