<?php

namespace App\Modules\CPL\Models;

use App\Modules\BoK\Models\Bok;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\Models\Kurikulum;
use App\Modules\Kurikulum\Models\ProfilLulusan;
use App\Support\Concerns\LogsSilogyActivity;
use Database\Factories\CplFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @use HasFactory<CplFactory>
 */
#[UseFactory(CplFactory::class)]
class Cpl extends Model
{
    /** @use HasFactory<CplFactory> */
    use HasFactory, HasUuids, LogsSilogyActivity;

    protected $table = 'cpl';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected $casts = [
        'domain' => 'array',
        'urutan' => 'integer',
    ];

    /**
     * Urutan tampil default = paling akhir dalam kurikulum yang sama,
     * kecuali sudah diisi eksplisit (mis. lewat drag-reorder). Berlaku
     * untuk semua jalur create (form, importer, seeder) karena lewat
     * event Eloquent.
     */
    protected static function booted(): void
    {
        static::creating(function (Cpl $cpl): void {
            if ($cpl->urutan !== null || blank($cpl->kurikulum_id)) {
                return;
            }

            $cpl->urutan = ((int) static::query()->where('kurikulum_id', $cpl->kurikulum_id)->max('urutan')) + 1;
        });
    }

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * @return BelongsTo<Kurikulum, $this>
     */
    public function kurikulum(): BelongsTo
    {
        return $this->belongsTo(Kurikulum::class);
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

    /**
     * @return BelongsToMany<ProfilLulusan, $this>
     */
    public function profilLulusan(): BelongsToMany
    {
        return $this->belongsToMany(
            ProfilLulusan::class,
            'cpl_profil_lulusan',
            'cpl_id',
            'profil_lulusan_id',
        )->withTimestamps();
    }

    /**
     * @return BelongsToMany<Bok, $this>
     */
    public function boks(): BelongsToMany
    {
        return $this->belongsToMany(
            Bok::class,
            'cpl_bok',
            'cpl_id',
            'bok_id',
        )->withPivot(['id', 'bobot'])->withTimestamps();
    }

    /**
     * CPL belum dipetakan ke profil lulusan maupun bahan kajian (BoK).
     */
    public function belumDiinteraksikan(): bool
    {
        return ! $this->cplProfilLulusan()->exists()
            && ! $this->cplBoks()->exists();
    }
}
