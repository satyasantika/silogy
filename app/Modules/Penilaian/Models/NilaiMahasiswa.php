<?php

namespace App\Modules\Penilaian\Models;

use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Support\Concerns\LogsSilogyActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NilaiMahasiswa extends Model
{
    use HasUuids, LogsSilogyActivity;

    protected $table = 'nilai_mahasiswas';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nilai' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<SubcpmkKomponenPenilaian, $this>
     */
    public function subcpmkKomponen(): BelongsTo
    {
        return $this->belongsTo(SubcpmkKomponenPenilaian::class, 'subcpmk_komponenpenilaian_id');
    }

    /**
     * @return BelongsTo<KelasMkMahasiswa, $this>
     */
    public function kelasMkMahasiswa(): BelongsTo
    {
        return $this->belongsTo(KelasMkMahasiswa::class);
    }
}
