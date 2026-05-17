<?php

namespace App\Modules\CPL\Models;

use App\Modules\Kurikulum\Models\ProfilLulusan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CplProfilLulusan extends Model
{
    use HasUuids;

    protected $table = 'cpl_profil_lulusan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Cpl, $this>
     */
    public function cpl(): BelongsTo
    {
        return $this->belongsTo(Cpl::class);
    }

    /**
     * @return BelongsTo<ProfilLulusan, $this>
     */
    public function profilLulusan(): BelongsTo
    {
        return $this->belongsTo(ProfilLulusan::class);
    }
}
