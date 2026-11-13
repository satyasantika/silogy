<?php

namespace App\Modules\Penilaian\Models;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use App\Support\Concerns\LogsSilogyActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Validasi nilai satu kelas oleh Validator Nilai. gugur_at terisi berarti data
 * laporan berubah sejak divalidasi (perlu validasi ulang).
 */
class ValidasiNilaiKelas extends Model
{
    use HasUuids, LogsSilogyActivity;

    protected $table = 'validasi_nilai_kelas';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'divalidasi_at' => 'datetime',
            'gugur_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<KelasMk, $this>
     */
    public function kelasMk(): BelongsTo
    {
        return $this->belongsTo(KelasMk::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validator_id');
    }
}
