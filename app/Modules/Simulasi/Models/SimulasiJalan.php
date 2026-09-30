<?php

namespace App\Modules\Simulasi\Models;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu kali pembangunan data simulasi, beserta seluruh jejak kepemilikannya.
 */
class SimulasiJalan extends Model
{
    use HasUuids;

    public const STATUS_BERJALAN = 'berjalan';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_GAGAL = 'gagal';

    public const STATUS_DIBONGKAR = 'dibongkar';

    protected $table = 'simulasi_jalan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'coba_peran' => 'boolean',
            'ringkasan' => 'array',
            'peringatan' => 'array',
            'mulai_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'dibongkar_pada' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SimulasiArtefak, $this>
     */
    public function artefak(): HasMany
    {
        return $this->hasMany(SimulasiArtefak::class, 'simulasi_jalan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dipicuOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dipicu_oleh_id');
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function sedangBerjalan(): bool
    {
        return $this->status === self::STATUS_BERJALAN;
    }

    /**
     * Sebuah jalan masih "ada" selama artefaknya belum dibongkar — termasuk
     * jalan yang gagal di tengah, karena justru itulah yang perlu dibersihkan.
     */
    public function masihAda(): bool
    {
        return in_array($this->status, [
            self::STATUS_BERJALAN,
            self::STATUS_SELESAI,
            self::STATUS_GAGAL,
        ], true);
    }
}
