<?php

namespace App\Modules\MK\Models;

use App\Modules\Kalender\Models\Semester;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Database\Factories\SubcpmkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read Cpmk|null $cpmk
 *
 * @use HasFactory<SubcpmkFactory>
 */
#[UseFactory(SubcpmkFactory::class)]
class Subcpmk extends Model
{
    /** @use HasFactory<SubcpmkFactory> */
    use HasFactory, HasUuids;

    protected $table = 'subcpmk';

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
     * CPMK diakses lewat pivot mk_cpmk (bukan kolom cpmk_id).
     *
     * @return Attribute<Cpmk|null, never>
     */
    protected function cpmk(): Attribute
    {
        return Attribute::get(fn (): ?Cpmk => $this->mkCpmk?->cpmk);
    }

    /**
     * @return BelongsTo<MkCpmk, $this>
     */
    public function mkCpmk(): BelongsTo
    {
        return $this->belongsTo(MkCpmk::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return HasMany<SubcpmkKomponenPenilaian, $this>
     */
    public function subcpmkKomponens(): HasMany
    {
        return $this->hasMany(SubcpmkKomponenPenilaian::class);
    }

    public function belumDiinteraksikan(): bool
    {
        return ! $this->subcpmkKomponens()->exists();
    }
}
