<?php

namespace Database\Seeders;

use App\Modules\Penilaian\Models\Evaluasi;
use App\Modules\Penilaian\Support\SelaraskanEvaluasiSiObe;
use Illuminate\Database\Seeder;

class EvaluasiSeeder extends Seeder
{
    /**
     * Master evaluasi default — komponen penilaian SI-OBE, berurutan
     * (lihat SelaraskanEvaluasiSiObe).
     *
     * @return list<array{kode: string, nama: string, kategori: string, workcloud: string, urutan: int}>
     */
    public static function evaluasiData(): array
    {
        $data = [];
        $urutan = 1;

        foreach (SelaraskanEvaluasiSiObe::DATA as $kode => $atribut) {
            $data[] = ['kode' => $kode, ...$atribut, 'urutan' => $urutan++];
        }

        return $data;
    }

    public function run(): void
    {
        foreach (self::evaluasiData() as $row) {
            Evaluasi::query()->updateOrCreate(
                ['kode' => $row['kode']],
                $row,
            );
        }
    }
}
