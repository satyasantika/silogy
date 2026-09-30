<?php

namespace App\Modules\MK\Models;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Enums\StatusPerubahanCpmk;
use App\Support\Concerns\LogsSilogyActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Usulan Koordinator MK untuk mengubah CPMK sebuah mata kuliah pada satu
 * semester, yang harus disetujui Tim Kurikulum unit pemilik MK.
 */
class PerubahanCpmkRequest extends Model
{
    use HasUuids, LogsSilogyActivity;

    protected $table = 'perubahan_cpmk_requests';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => StatusPerubahanCpmk::class,
            'ringkasan_usulan' => 'array',
            'ditinjau_pada' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Mk, $this>
     */
    public function mk(): BelongsTo
    {
        return $this->belongsTo(Mk::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function diajukanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function ditinjauOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditinjau_oleh_id');
    }

    public function menunggu(): bool
    {
        return $this->status === StatusPerubahanCpmk::Diajukan;
    }

    public function disetujui(): bool
    {
        return $this->status === StatusPerubahanCpmk::Disetujui;
    }
}
