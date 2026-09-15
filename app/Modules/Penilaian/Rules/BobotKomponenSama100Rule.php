<?php

namespace App\Modules\Penilaian\Rules;

use App\Modules\Penilaian\Models\KomponenPenilaian;
use App\Modules\Penilaian\Models\KomponenPenilaianSemester;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BobotKomponenSama100Rule implements ValidationRule
{
    public function __construct(
        private readonly string $mkId,
        private readonly string $semesterId,
        private readonly ?string $kodeAsesmen,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $total = static::totalBobot($this->mkId, $this->semesterId, $this->kodeAsesmen, (float) $value);

        if (abs($total - 100) > 0.01) {
            $fail(sprintf(
                'Total bobot komponen pada mata kuliah ini harus 100%% (saat ini: %.2f%%).',
                $total,
            ));
        }
    }

    /**
     * Total bobot komponen penilaian pada satu mata kuliah + semester,
     * ditambah nilai yang sedang diisi/diedit (belum tersimpan). Dipakai
     * juga untuk menampilkan ringkasan bobot secara realtime pada form.
     */
    public static function totalBobot(string $mkId, string $semesterId, ?string $kodeAsesmen, float $tambahan = 0): float
    {
        // Bobot dijumlahkan dari pivot semester: satu Asesmen kini boleh
        // berlaku di beberapa semester dengan bobot berbeda-beda.
        $existing = KomponenPenilaianSemester::query()
            ->where('komponen_penilaian_semester.semester_id', $semesterId)
            ->whereIn(
                'komponen_penilaian_semester.komponen_penilaian_id',
                KomponenPenilaian::query()
                    ->select('id')
                    ->where('mk_id', $mkId)
                    ->when(
                        filled($kodeAsesmen),
                        fn ($query) => $query->where('kode', '!=', $kodeAsesmen),
                    ),
            )
            ->sum('bobot');

        return (float) $existing + $tambahan;
    }
}
