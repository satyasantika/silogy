<?php

namespace App\Modules\Penilaian\Rules;

use App\Modules\Penilaian\Models\KomponenPenilaian;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BobotKomponenSama100Rule implements ValidationRule
{
    public function __construct(
        private readonly string $kelasMkId,
        private readonly ?string $ignoreKomponenId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $existing = KomponenPenilaian::query()
            ->where('kelas_mk_id', $this->kelasMkId)
            ->when(
                $this->ignoreKomponenId,
                fn ($query) => $query->where('id', '!=', $this->ignoreKomponenId),
            )
            ->sum('bobot');

        $total = (float) $existing + (float) $value;

        if (abs($total - 100) > 0.01) {
            $fail(sprintf(
                'Total bobot komponen per kelas harus 100%% (saat ini: %.2f%%).',
                $total,
            ));
        }
    }
}
