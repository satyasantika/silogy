<?php

namespace App\Modules\CPL\Models;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Support\Concerns\LogsSilogyActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kode CPL milik unit lain (universitas/fakultas), diseragamkan tampilan
 * kodenya khusus untuk satu prodi — tidak mengubah Cpl::kode asli.
 */
class CplKodeOverride extends Model
{
    use HasUuids, LogsSilogyActivity;

    protected $table = 'cpl_kode_overrides';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * @return BelongsTo<Cpl, $this>
     */
    public function cpl(): BelongsTo
    {
        return $this->belongsTo(Cpl::class);
    }
}
