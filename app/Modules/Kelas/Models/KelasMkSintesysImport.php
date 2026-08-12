<?php

namespace App\Modules\Kelas\Models;

use App\Models\User;
use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kalender\Models\Semester;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KelasMkSintesysImport extends Model
{
    use HasUuids;

    protected $table = 'kelas_mk_sintesys_imports';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'processed' => 'integer',
            'kelas_dibuat' => 'integer',
            'kelas_diperbarui' => 'integer',
            'peserta_terdaftar' => 'integer',
            'peserta_sudah_terdaftar' => 'integer',
            'peserta_dihapus' => 'integer',
            'errors' => 'array',
        ];
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
    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
