<?php

namespace App\Modules\Kalender\Models;

use App\Modules\Kalender\Observers\SemesterObserver;
use App\Modules\Kelas\Models\KelasMk;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(SemesterObserver::class)]
class Semester extends Model
{
    use HasUuids;

    protected $table = 'semesters';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tahun_mulai' => 'integer',
            'tahun_selesai' => 'integer',
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'status_aktif' => 'boolean',
        ];
    }

    public function kelasMks(): HasMany
    {
        return $this->hasMany(KelasMk::class);
    }

    /**
     * kelas_mk.semester_id -> semesters bersifat restrictOnDelete — cek ini
     * dulu supaya penghapusan ditolak dengan UX yang jelas, bukan QueryException.
     */
    public function sedangDigunakan(): bool
    {
        return $this->kelasMks()->exists();
    }
}
