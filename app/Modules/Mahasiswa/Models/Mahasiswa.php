<?php

namespace App\Modules\Mahasiswa\Models;

use App\Modules\Institusi\Models\AcademicUnit;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use Database\Factories\MahasiswaFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @use HasFactory<MahasiswaFactory>
 *
 * @property-read KelasMkMahasiswa|null $pivot
 */
#[UseFactory(MahasiswaFactory::class)]
class Mahasiswa extends Model
{
    /** @use HasFactory<MahasiswaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'mahasiswas';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<AcademicUnit, $this>
     */
    public function academicUnit(): BelongsTo
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    /**
     * Inverse dari KelasMk::mahasiswas() — dibutuhkan Filament AttachAction
     * untuk mengecualikan kelas yang sudah diikuti dari daftar pilihan.
     *
     * @return BelongsToMany<KelasMk, $this, KelasMkMahasiswa>
     */
    public function kelasMks(): BelongsToMany
    {
        return $this->belongsToMany(KelasMk::class, 'kelas_mk_mahasiswa')
            ->using(KelasMkMahasiswa::class)
            ->withPivot(['nilai_angka', 'nilai_huruf'])
            ->withTimestamps();
    }
}
