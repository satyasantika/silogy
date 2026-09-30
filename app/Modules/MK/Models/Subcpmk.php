<?php

namespace App\Modules\MK\Models;

use App\Modules\Kalender\Models\Semester;
use App\Modules\Penilaian\Models\SubcpmkKomponenPenilaian;
use Database\Factories\SubcpmkFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property-read Cpmk|null $cpmk
 *
 * @use HasFactory<SubcpmkFactory>
 */
#[UseFactory(SubcpmkFactory::class)]
class Subcpmk extends Model
{
    /** @use HasFactory<SubcpmkFactory> */
    use HasFactory, HasUuids;

    protected $table = 'subcpmk';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    /**
     * CPMK diakses lewat pivot mk_cpmk (bukan kolom cpmk_id).
     *
     * @return Attribute<Cpmk|null, never>
     */
    protected function cpmk(): Attribute
    {
        return Attribute::get(fn (): ?Cpmk => $this->mkCpmk?->cpmk);
    }

    /**
     * @return BelongsTo<MkCpmk, $this>
     */
    public function mkCpmk(): BelongsTo
    {
        return $this->belongsTo(MkCpmk::class);
    }

    /**
     * Semester tempat Sub-CPMK ini berlaku, beserta bobotnya per semester.
     * Satu baris Sub-CPMK boleh terlampir di banyak semester — itulah bentuk
     * "pakai Sub-CPMK semester lalu" tanpa membuat ID baru.
     *
     * @return BelongsToMany<Semester, $this>
     */
    public function semesters(): BelongsToMany
    {
        return $this->belongsToMany(Semester::class, 'subcpmk_semester')
            ->withPivot('bobot')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Subcpmk>  $query
     * @return Builder<Subcpmk>
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
     * Bobot Sub-CPMK pada satu semester — nilai turunan yang dihitung ulang
     * dari pemetaannya ke Asesmen di semester tersebut.
     */
    public function bobotUntukSemester(string $semesterId): ?float
    {
        $pivot = SubcpmkSemester::query()
            ->where('subcpmk_id', $this->id)
            ->where('semester_id', $semesterId)
            ->first();

        return $pivot?->bobot;
    }

    /**
     * @return HasMany<SubcpmkKomponenPenilaian, $this>
     */
    public function subcpmkKomponens(): HasMany
    {
        return $this->hasMany(SubcpmkKomponenPenilaian::class);
    }

    /**
     * Penjaga migrasi: kolom subcpmk.bobot dihapus karena bobot kini
     * bergantung semester (satu baris Sub-CPMK dipakai beberapa semester
     * dengan asesmen berbeda). Tanpa penjaga ini pemanggil lama akan diam-
     * diam membaca null lalu menganggapnya 0.
     *
     * @return Attribute<never, never>
     */
    protected function bobot(): Attribute
    {
        return Attribute::get(function (): never {
            throw new LogicException(
                'Subcpmk::$bobot sudah dihapus — bobot Sub-CPMK kini per semester. '
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
