<?php

namespace App\Modules\Penilaian\Models;

use App\Modules\Kalender\Models\Semester;
use App\Modules\MK\Models\Mk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class KomponenPenilaian extends Model
{
    use HasUuids;

    protected $table = 'komponen_penilaian';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Mk, $this>
     */
    public function mk(): BelongsTo
    {
        return $this->belongsTo(Mk::class);
    }

    /**
     * Semester tempat Asesmen ini berlaku, beserta bobotnya terhadap nilai
     * akhir MK pada semester tersebut. Satu baris Asesmen boleh terlampir di
     * banyak semester — itulah bentuk "pakai tagihan semester lalu".
     *
     * @return BelongsToMany<Semester, $this>
     */
    public function semesters(): BelongsToMany
    {
        return $this->belongsToMany(Semester::class, 'komponen_penilaian_semester')
            ->withPivot('bobot')
            ->withTimestamps();
    }

    /**
     * @param  Builder<KomponenPenilaian>  $query
     * @return Builder<KomponenPenilaian>
     */
    public function scopeUntukSemester(Builder $query, string $semesterId): Builder
    {
        return $query->whereHas(
            'semesters',
            fn (Builder $semester): Builder => $semester->whereKey($semesterId),
        );
    }

    /**
     * Saringan semester untuk Builder yang tipe generiknya sudah luruh jadi
     * Builder<Model> — terjadi pada callback tabel Filament, di mana scope
     * tidak bisa diresolusi. Satu implementasi dipakai keduanya supaya nama
     * relasi pivot tidak tersebar.
     *
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public static function saringSemester(Builder $query, string $semesterId): Builder
    {
        return $query->whereHas(
            'semesters',
            fn (Builder $semester): Builder => $semester->whereKey($semesterId),
        );
    }

    /**
     * Bobot Asesmen ini terhadap nilai akhir MK pada satu semester. 0 bila
     * Asesmen tidak berlaku di semester tersebut.
     */
    public function bobotUntukSemester(string $semesterId): float
    {
        $pivot = KomponenPenilaianSemester::query()
            ->where('komponen_penilaian_id', $this->id)
            ->where('semester_id', $semesterId)
            ->first();

        return $pivot === null ? 0.0 : (float) $pivot->bobot;
    }

    /**
     * @return BelongsTo<Evaluasi, $this>
     */
    public function evaluasi(): BelongsTo
    {
        return $this->belongsTo(Evaluasi::class);
    }

    /**
     * @return HasMany<SubcpmkKomponenPenilaian, $this>
     */
    public function subcpmkKomponens(): HasMany
    {
        return $this->hasMany(SubcpmkKomponenPenilaian::class, 'komponen_penilaian_id');
    }

    /**
     * Penjaga migrasi: kolom komponen_penilaian.bobot dihapus karena bobot
     * kini bergantung semester (satu Asesmen dipakai beberapa semester
     * dengan bobot berbeda). Tanpa penjaga ini pemanggil lama akan diam-diam
     * membaca null lalu menganggapnya 0.
     *
     * @return Attribute<never, never>
     */
    protected function bobot(): Attribute
    {
        return Attribute::get(function (): never {
            throw new LogicException(
                'KomponenPenilaian::$bobot sudah dihapus — bobot Asesmen kini per semester. '
                .'Gunakan bobotUntukSemester($semesterId) atau relasi semesters()->pivot->bobot.',
            );
        });
    }

    public function belumDiinteraksikan(?string $semesterId = null): bool
    {
        return ! $this->subcpmkKomponens()
            ->when(filled($semesterId), fn ($query) => $query->where('semester_id', $semesterId))
            ->exists();
    }
}
