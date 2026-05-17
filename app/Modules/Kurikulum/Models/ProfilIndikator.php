<?php

namespace App\Modules\Kurikulum\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilIndikator extends Model
{
    use HasUuids;

    protected $table = 'profil_indikators';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<ProfilLulusan, $this>
     */
    public function profilLulusan(): BelongsTo
    {
        return $this->belongsTo(ProfilLulusan::class, 'profil_id');
    }
}
