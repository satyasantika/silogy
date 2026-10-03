<?php

namespace App\Modules\MK\Models;

use App\Modules\Kalender\Models\Semester;
use App\Modules\Simulasi\Models\Concerns\BerRanahSimulasi;
use Database\Factories\CpmkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @use HasFactory<CpmkFactory>
 */
#[UseFactory(CpmkFactory::class)]
class Cpmk extends Model
{
    use BerRanahSimulasi;

    public const RANAH_MODE = 'mk';

    public const RANAH_KOLOM = 'mk_id';

    /** @use HasFactory<CpmkFactory> */
    use HasFactory, HasUuids;

    protected $table = 'cpmk';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Mk, $this>
     */
    public function mk(): BelongsTo
    {
        return $this->belongsTo(Mk::class);
    }

    /**
     * @return HasMany<MkCpmk, $this>
     */
    public function mkCpmks(): HasMany
    {
        return $this->hasMany(MkCpmk::class);
    }

    /**
     * @return HasManyThrough<Subcpmk, MkCpmk, $this>
     */
    public function subcpmks(): HasManyThrough
    {
        return $this->hasManyThrough(Subcpmk::class, MkCpmk::class);
    }

    /**
     * Semester tempat CPMK ini berlaku. Satu baris CPMK boleh terlampir di
     * banyak semester — itulah bentuk "pakai CPMK semester lalu".
     *
     * @return BelongsToMany<Semester, $this>
     */
    public function semesters(): BelongsToMany
    {
        return $this->belongsToMany(Semester::class, 'cpmk_semester')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Cpmk>  $query
     * @return Builder<Cpmk>
     */
    public function scopeUntukSemester(Builder $query, string $semesterId): Builder
    {
        return $query->whereHas(
            'semesters',
            fn (Builder $semester): Builder => $semester->whereKey($semesterId),
        );
    }

    /**
     * Saringan semester untuk Builder yang tipe generiknya sudah luruh jadi
     * Builder<Model> — terjadi pada callback tabel Filament, di mana scope
     * tidak bisa diresolusi. Satu implementasi dipakai keduanya supaya nama
     * relasi pivot tidak tersebar.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function saringSemester(Builder $query, string $semesterId): Builder
    {
        return $query->whereHas(
            'semesters',
            fn (Builder $semester): Builder => $semester->whereKey($semesterId),
        );
    }

    /**
     * CPMK belum dipetakan ke CPL (belum ada baris mk_cpmk).
     */
    public function belumDiinteraksikan(): bool
    {
        return ! $this->mkCpmks()->exists();
    }
}
