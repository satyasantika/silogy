<?php

namespace App\Modules\Penilaian\Models;

use App\Models\User;
use App\Modules\Kelas\Models\KelasMk;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan evaluasi & RTL satu kelas: cpl_id terisi = analisis + RTL untuk
 * CPL yang tidak tercapai; cpl_id null = catatan umum kelas.
 */
class CatatanRtlKelas extends Model
{
    use HasUuids;

    protected $table = 'catatan_rtl_kelas';

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
     * @return BelongsTo<User, $this>
     */
    public function diubahOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh_id');
    }
}
