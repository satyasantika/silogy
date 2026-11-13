<?php

namespace App\Modules\Penilaian\Services;

/**
 * Sidik (sha256) bentuk kanonik data laporan kelas: stabil terhadap urutan
 * kunci dan tipe angka, peka terhadap nilai dan urutan baris.
 */
class SidikLaporanKelas
{
    /**
     * @param  array<mixed>  $data
     */
    public function dariData(array $data): string
    {
        return hash('sha256', json_encode($this->kanonik($data), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function kanonik(mixed $nilai): mixed
    {
        if (is_array($nilai)) {
            if (array_is_list($nilai)) {
                return array_map($this->kanonik(...), $nilai);
            }

            ksort($nilai);

            return array_map($this->kanonik(...), $nilai);
        }

        if (is_bool($nilai)) {
            return $nilai ? 'true' : 'false';
        }

        if (is_numeric($nilai)) {
            return number_format((float) $nilai, 4, '.', '');
        }

        return $nilai;
    }
}
