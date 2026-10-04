<?php

namespace App\Modules\Simulasi\Models;

use App\Models\User;
use App\Modules\Kalender\Models\Semester;
use App\Modules\Simulasi\Support\AkunSimulasi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Satu sandbox simulasi: satu kali pembangunan paket data beserta seluruh
 * jejak kepemilikannya. `id` sekaligus menjadi `sandbox_id` pada data akarnya.
 *
 * `pengunjung` kosong berarti sandbox masih siap di kolam; terisi berarti
 * sudah diklaim satu pengunjung (hash cookie, bukan data pribadi).
 *
 * @property Carbon|null $terakhir_aktif_pada
 * @property string $mode
 * @property bool $bersama
 * @property int $jumlah_mk
 */
class SimulasiJalan extends Model
{
    use HasUuids;

    public const STATUS_BERJALAN = 'berjalan';

    public const STATUS_SELESAI = 'selesai';

    public const STATUS_GAGAL = 'gagal';

    public const STATUS_DIBONGKAR = 'dibongkar';

    /** Kurikulum sampai nilai sudah terisi: contoh hasil akhir. */
    public const MODE_TERISI = 'terisi';

    /** Satu prodi dengan satu kurikulum dan satu MK kosong: pengunjung mengisi sendiri. */
    public const MODE_KOSONG = 'kosong';

    /** @var list<string> */
    public const MODE = [self::MODE_TERISI, self::MODE_KOSONG];

    protected $table = 'simulasi_jalan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'coba_peran' => 'boolean',
            'bersama' => 'boolean',
            'ringkasan' => 'array',
            'peringatan' => 'array',
            'mulai_pada' => 'datetime',
            'selesai_pada' => 'datetime',
            'dibongkar_pada' => 'datetime',
            'terakhir_aktif_pada' => 'datetime',
        ];
    }

    /**
     * Sandbox yang artefaknya masih ada, termasuk yang gagal di tengah
     * karena justru itu yang perlu dibersihkan.
     *
     * @param  Builder<SimulasiJalan>  $query
     * @return Builder<SimulasiJalan>
     */
    public function scopeMasihAda(Builder $query): Builder
    {
        return $query->whereIn('status', [
            self::STATUS_BERJALAN,
            self::STATUS_SELESAI,
            self::STATUS_GAGAL,
        ]);
    }

    /**
     * @param  Builder<SimulasiJalan>  $query
     * @return Builder<SimulasiJalan>
     */
    public function scopeSiap(Builder $query, ?string $mode = null): Builder
    {
        return $query->where('status', self::STATUS_SELESAI)
            ->whereNull('pengunjung')
            ->where('bersama', false)
            ->when($mode !== null, fn (Builder $q) => $q->where('mode', $mode));
    }

    /**
     * Contoh terisi bersama: satu salinan hanya-baca untuk semua pengunjung.
     *
     * @param  Builder<SimulasiJalan>  $query
     * @return Builder<SimulasiJalan>
     */
    public function scopeBersama(Builder $query): Builder
    {
        return $query->where('bersama', true);
    }

    /**
     * @return HasMany<SimulasiArtefak, $this>
     */
    public function artefak(): HasMany
    {
        return $this->hasMany(SimulasiArtefak::class, 'simulasi_jalan_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function dipicuOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dipicu_oleh_id');
    }

    /**
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class, 'semester_id');
    }

    public function kode(): string
    {
        return AkunSimulasi::kode((string) $this->getKey());
    }

    public function kosong(): bool
    {
        return $this->mode === self::MODE_KOSONG;
    }

    public function sedangBerjalan(): bool
    {
        return $this->status === self::STATUS_BERJALAN;
    }

    public function masihAda(): bool
    {
        return in_array($this->status, [
            self::STATUS_BERJALAN,
            self::STATUS_SELESAI,
            self::STATUS_GAGAL,
        ], true);
    }
}
