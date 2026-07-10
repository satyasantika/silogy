<?php

namespace App\Modules\Penilaian\Rules;

use App\Modules\Penilaian\Models\KomponenPenilaian;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class BobotKomponenSama100Rule implements ValidationRule
{
    /**
     * @param  array<int, string>  $kelasMkIds  Semua kelas dalam konteks mata kuliah + semester (satu kelas saja bila mode lama).
     */
    public function __construct(
        private readonly array $kelasMkIds,
        private readonly ?string $kodeAsesmen,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $total = static::totalBobot($this->kelasMkIds, $this->kodeAsesmen, (float) $value);

        if (abs($total - 100) > 0.01) {
            $fail(sprintf(
                'Total bobot komponen pada mata kuliah ini harus 100%% (saat ini: %.2f%%).',
                $total,
            ));
        }
    }

    /**
     * Total bobot komponen penilaian pada satu mata kuliah + semester
     * (dihitung sekali per kode asesmen, bukan dijumlah per kelas — sebab
     * asesmen yang sama diterapkan ke semua kelasnya), ditambah nilai yang
     * sedang diisi/diedit (belum tersimpan). Dipakai juga untuk menampilkan
     * ringkasan bobot secara realtime pada form.
     *
     * @param  array<int, string>  $kelasMkIds
     */
    public static function totalBobot(array $kelasMkIds, ?string $kodeAsesmen, float $tambahan = 0): float
    {
        $perKode = KomponenPenilaian::query()
            ->whereIn('kelas_mk_id', $kelasMkIds)
            ->when(
                filled($kodeAsesmen),
                fn ($query) => $query->where('kode', '!=', $kodeAsesmen),
            )
            ->selectRaw('MAX(bobot) as bobot')
            ->groupBy('kode');

        $existing = DB::query()->fromSub($perKode, 'per_kode')->sum('bobot');

        return (float) $existing + $tambahan;
    }
}
