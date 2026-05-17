<?php

namespace App\Modules\Kelas\Models;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\MkUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasMk extends Model
{
    use HasUuids;

    protected $table = 'kelas_mk';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'kapasitas' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<MkUnit, $this>
     */
    public function mkUnit(): BelongsTo
    {
        return $this->belongsTo(MkUnit::class);
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dosenPengampu(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dosen_pengampu_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function koordinatorMk(): BelongsTo
    {
        return $this->belongsTo(User::class, 'koordinator_mk_id');
    }

    /**
     * @return HasMany<KelasMkMahasiswa, $this>
     */
    public function kelasMkMahasiswas(): HasMany
    {
        return $this->hasMany(KelasMkMahasiswa::class);
    }

    /**
     * @return BelongsToMany<Mahasiswa, $this, KelasMkMahasiswa>
     */
    public function mahasiswas(): BelongsToMany
    {
        return $this->belongsToMany(Mahasiswa::class, 'kelas_mk_mahasiswa')
            ->using(KelasMkMahasiswa::class)
            ->withPivot(['nilai_angka', 'nilai_huruf'])
            ->withTimestamps();
    }
}
