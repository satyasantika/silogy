<?php

namespace App\Modules\Simulasi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris yang benar-benar DIBUAT oleh sebuah jalan simulasi.
 *
 * Baris yang hanya diadopsi (firstOrCreate menemukan yang sudah ada) sengaja
 * tidak pernah dicatat di sini — itulah yang membuat "hapus simulasi tidak
 * menyentuh data nyata" menjadi sifat struktural, bukan tebakan.
 */
class SimulasiArtefak extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'simulasi_artefak';

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<SimulasiJalan, $this>
     */
    public function jalan(): BelongsTo
    {
        return $this->belongsTo(SimulasiJalan::class, 'simulasi_jalan_id');
    }
}
