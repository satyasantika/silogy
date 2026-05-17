<?php

namespace App\Modules\Kurikulum\Models;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kurikulum extends Model
{
    use HasUuids;

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
}
