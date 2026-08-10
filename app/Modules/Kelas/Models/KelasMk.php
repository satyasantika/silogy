<?php

namespace App\Modules\Kelas\Models;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Support\Concerns\LogsSilogyActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasMk extends Model
{
    use HasUuids, LogsSilogyActivity;

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
     * Penugasan dianggap selesai ketika koordinator MK sudah menyusun
     * komponen penilaian dengan total bobot 100% dan setiap komponen
     * terpetakan ke minimal satu Sub-CPMK — prasyarat dosen menilai.
     */
    public function penugasanSelesai(): bool
    {
        $this->loadMissing('mkUnit');

        $mkId = $this->mkUnit?->mk_id;

        if ($mkId === null) {
            return false;
        }

        $komponens = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->where('semester_id', $this->semester_id)
            ->withCount('subcpmkKomponens')
            ->get();

        if ($komponens->isEmpty()) {
            return false;
        }

        // Toleransi kecil, bukan perbandingan float ketat: sum() atas nilai
        // decimal:2 lewat operator + PHP bisa menghasilkan double seperti
        // 99.99999999999998 walau totalnya genuinely 100.00, sehingga
        // rencana yang sebenarnya sudah selesai bisa salah terblokir.
        if (abs((float) $komponens->sum('bobot') - 100.0) > 0.005) {
            return false;
        }

        return $komponens->every(
            fn ($komponen): bool => $komponen->subcpmk_komponens_count > 0,
        );
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
