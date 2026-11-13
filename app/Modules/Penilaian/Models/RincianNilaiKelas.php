<?php

namespace App\Modules\Penilaian\Models;

use App\Modules\Kelas\Models\KelasMk;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penanda bahwa nilai satu asesmen di satu kelas dirinci per Sub-CPMK.
 */
class RincianNilaiKelas extends Model
{
    use HasUuids;

    protected $table = 'rincian_nilai_kelas';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<KelasMk, $this>
     */
    public function kelasMk(): BelongsTo
    {
        return $this->belongsTo(KelasMk::class);
    }

    /**
     * @return BelongsTo<KomponenPenilaian, $this>
     */
    public function komponenPenilaian(): BelongsTo
    {
        return $this->belongsTo(KomponenPenilaian::class);
    }
}
