<?php

namespace App\Modules\Kalkulasi\Models;

use App\Modules\CPL\Models\Cpl;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Models\Mk;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilCplMkUnit extends Model
{
    use HasUuids;

    protected $table = 'hasil_cpl_mk_unit';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'rata_rata' => 'decimal:2',
            'persentase_tercapai' => 'decimal:2',
            'jumlah_mahasiswa' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Cpl, $this>
     */
    public function cpl(): BelongsTo
    {
        return $this->belongsTo(Cpl::class);
    }

    /**
     * @return BelongsTo<Mk, $this>
     */
    public function mk(): BelongsTo
    {
        return $this->belongsTo(Mk::class);
    }

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
