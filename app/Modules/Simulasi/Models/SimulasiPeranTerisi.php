<?php

namespace App\Modules\Simulasi\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu peran yang sedang dipegang sebuah tab di dalam ruang latihan.
 * Kunci unik (simulasi_jalan_id, peran) menjamin satu pemegang per peran.
 */
class SimulasiPeranTerisi extends Model
{
    use HasUuids;

    protected $table = 'simulasi_peran_terisi';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['terakhir_aktif_pada' => 'datetime'];
    }
}
