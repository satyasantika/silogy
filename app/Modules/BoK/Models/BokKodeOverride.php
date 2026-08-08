<?php

namespace App\Modules\BoK\Models;

use App\Modules\Institusi\Models\AcademicUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kode BoK milik unit lain (universitas/fakultas), diseragamkan tampilan
 * kodenya khusus untuk satu prodi — tidak mengubah Bok::kode asli.
 */
class BokKodeOverride extends Model
{
    use HasUuids;

    protected $table = 'bok_kode_overrides';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected $casts = [
        'urutan' => 'integer',
    ];

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * @return BelongsTo<Bok, $this>
     */
    public function bok(): BelongsTo
    {
        return $this->belongsTo(Bok::class);
    }
}
