<?php

namespace App\Modules\Kalkulasi\Models;

use App\Modules\CPL\Models\Cpl;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilCplMk extends Model
{
    use HasUuids;

    protected $table = 'hasil_cpl_mk';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nilai_akhir' => 'decimal:2',
            'nilai_berbobot' => 'decimal:2',
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
     * @return BelongsTo<MkUnit, $this>
     */
    public function mkUnit(): BelongsTo
    {
        return $this->belongsTo(MkUnit::class);
    }

    /**
     * @return BelongsTo<KelasMkMahasiswa, $this>
     */
    public function kelasMkMahasiswa(): BelongsTo
    {
        return $this->belongsTo(KelasMkMahasiswa::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }
}
