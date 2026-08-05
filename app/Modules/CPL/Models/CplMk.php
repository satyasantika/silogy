<?php

namespace App\Modules\CPL\Models;

use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkCpmk;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CplMk extends Model
{
    use HasUuids;

    protected $table = 'cpl_mk';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    public const DESIMAL_BOBOT = 1;

    protected function casts(): array
    {
        return [
            'bobot' => 'decimal:2',
        ];
    }

    public static function keSepersepuluh(float|int|string|null $bobot): int
    {
        return (int) round(round((float) $bobot, self::DESIMAL_BOBOT) * 10);
    }

    public static function dariSepersepuluh(int $sepersepuluh): float
    {
        return $sepersepuluh / 10;
    }

    /**
     * Jumlah bobot pada skala satu desimal (hindari 33,30+33,33+33,34 = 99,97 → 100).
     *
     * @param  iterable<float|int|string|null>  $bobots
     */
    public static function jumlahBobot(iterable $bobots): float
    {
        $total = 0;

        foreach ($bobots as $bobot) {
            $total += self::keSepersepuluh($bobot);
        }

        return self::dariSepersepuluh($total);
    }

    /**
     * Sisa kuota bobot MK terhadap CPL (target 100%) di luar pivot
     * $excludeCplBokId bila sedang mengedit sel yang sudah terisi.
     */
    public static function sisaBobotTersedia(string $mkId, ?string $excludeCplBokId = null): float
    {
        $terpakai = (int) static::query()
            ->where('mk_id', $mkId)
            ->when(
                $excludeCplBokId !== null,
                fn ($query) => $query->where('cpl_bok_id', '!=', $excludeCplBokId),
            )
            ->pluck('bobot')
            ->sum(fn (mixed $bobot): int => self::keSepersepuluh($bobot));

        return self::dariSepersepuluh(max(0, 1000 - $terpakai));
    }

    public static function rekapPas(float $total): bool
    {
        return self::keSepersepuluh($total) === 1000;
    }

    /**
     * @return BelongsTo<CplBok, $this>
     */
    public function cplBok(): BelongsTo
    {
        return $this->belongsTo(CplBok::class);
    }

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
}
