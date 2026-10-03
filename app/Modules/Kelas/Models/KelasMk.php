<?php

namespace App\Modules\Kelas\Models;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Mahasiswa\Models\Mahasiswa;
use App\Modules\MK\Models\MkUnit;
use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Simulasi\Models\Concerns\BerRanahSimulasi;
use App\Support\Concerns\LogsSilogyActivity;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasMk extends Model
{
    use BerRanahSimulasi;

    public const RANAH_MODE = 'mk_unit';

    public const RANAH_KOLOM = 'mk_unit_id';

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

        $semesterId = (string) $this->semester_id;

        // Bobot maupun pemetaan Sub-CPMK disaring per semester: satu Asesmen
        // boleh dipakai ulang di semester lain dengan bobot dan pemetaan
        // berbeda, jadi menghitung lintas semester akan salah.
        $komponens = KomponenPenilaian::query()
            ->where('mk_id', $mkId)
            ->untukSemester($semesterId)
            ->withCount([
                'subcpmkKomponens' => fn ($query) => $query->where('semester_id', $semesterId),
            ])
            ->get();

        if ($komponens->isEmpty()) {
            return false;
        }

        $totalBobot = $komponens->sum(
            fn (KomponenPenilaian $komponen): float => $komponen->bobotUntukSemester($semesterId),
        );

        // Toleransi kecil, bukan perbandingan float ketat: sum() atas nilai
        // decimal:2 lewat operator + PHP bisa menghasilkan double seperti
        // 99.99999999999998 walau totalnya genuinely 100.00, sehingga
        // rencana yang sebenarnya sudah selesai bisa salah terblokir.
        if (abs((float) $totalBobot - 100.0) > 0.005) {
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
