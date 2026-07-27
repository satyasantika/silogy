<?php

namespace App\Modules\Kurikulum\Models;

use App\Models\User;
use App\Modules\BoK\Models\Bok;
use App\Modules\CPL\Models\Cpl;
use App\Modules\CPL\Models\CplBok;
use App\Modules\CPL\Models\CplMk;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kurikulum\States\KurikulumState;
use App\Modules\MK\Models\Mk;
use App\Modules\MK\Models\MkUnit;
use App\Support\Concerns\LogsSilogyActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\ModelStates\HasStates;

class Kurikulum extends Model
{
    use HasStates, HasUuids, LogsSilogyActivity;

    protected $table = 'kurikulum';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'target_capaian_lulusan' => 'integer',
            'is_active' => 'boolean',
            'state' => KurikulumState::class,
        ];
    }

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /**
     * @return HasMany<ProfilLulusan, $this>
     */
    public function profilLulusan(): HasMany
    {
        return $this->hasMany(ProfilLulusan::class);
    }

    /**
     * @return HasMany<Cpl, $this>
     */
    public function cpls(): HasMany
    {
        return $this->hasMany(Cpl::class);
    }

    /**
     * @return HasMany<Bok, $this>
     */
    public function boks(): HasMany
    {
        return $this->hasMany(Bok::class);
    }

    /**
     * @return HasMany<Mk, $this>
     */
    public function mks(): HasMany
    {
        return $this->hasMany(Mk::class);
    }

    /**
     * @return HasMany<MkUnit, $this>
     */
    public function mkUnits(): HasMany
    {
        return $this->hasMany(MkUnit::class);
    }

    /**
     * @return HasMany<CplBok, $this>
     */
    public function cplBoks(): HasManyThrough
    {
        return $this->hasManyThrough(
            CplBok::class,
            Cpl::class,
            'kurikulum_id',
            'cpl_id',
            'id',
            'id',
        );
    }

    /**
     * @return HasMany<CplMk, $this>
     */
    public function cplMks(): HasMany
    {
        return $this->hasMany(CplMk::class, 'cpl_bok_id')
            ->whereIn('cpl_bok_id', function ($query): void {
                $query->select('cpl_bok.id')
                    ->from('cpl_bok')
                    ->join('cpl', 'cpl.id', '=', 'cpl_bok.cpl_id')
                    ->whereColumn('cpl.kurikulum_id', 'kurikulum.id');
            });
    }

    /**
     * @return HasMany<StateTransition, $this>
     */
    public function stateTransitions(): HasMany
    {
        return $this->hasMany(StateTransition::class, 'model_id')
            ->where('model_type', self::class);
    }

    /**
     * Kurikulum masih hanya metadata awal — belum ada baris turunan
     * (profil, CPL, BoK, MK, penawaran MK).
     */
    public function belumDigunakanDiTabelLain(): bool
    {
        return ! $this->profilLulusan()->exists()
            && ! $this->cpls()->exists()
            && ! $this->boks()->exists()
            && ! $this->mks()->exists()
            && ! $this->mkUnits()->exists();
    }
}
