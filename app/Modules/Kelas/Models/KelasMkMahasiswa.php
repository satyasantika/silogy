<?php

namespace App\Modules\Kelas\Models;

use App\Modules\Mahasiswa\Models\Mahasiswa;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class KelasMkMahasiswa extends Pivot
{
    use HasUuids;

    protected $table = 'kelas_mk_mahasiswa';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'kelas_mk_id',
        'mahasiswa_id',
        'nilai_angka',
        'nilai_huruf',
    ];

    protected function casts(): array
    {
        return [
            'nilai_angka' => 'decimal:2',
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
     * @return BelongsTo<Mahasiswa, $this>
     */
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }
}
