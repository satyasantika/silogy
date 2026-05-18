<?php

namespace App\Modules\Kalkulasi\Models;

use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\MK\Models\Cpmk;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilCpmk extends Model
{
    use HasUuids;

    protected $table = 'hasil_cpmk';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nilai_akhir' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Cpmk, $this>
     */
    public function cpmk(): BelongsTo
    {
        return $this->belongsTo(Cpmk::class);
    }

    /**
     * @return BelongsTo<KelasMkMahasiswa, $this>
     */
    public function kelasMkMahasiswa(): BelongsTo
    {
        return $this->belongsTo(KelasMkMahasiswa::class);
    }

    /**
     * @return BelongsTo<KelasMk, $this>
     */
    public function kelasMk(): BelongsTo
    {
        return $this->belongsTo(KelasMk::class);
    }
}
