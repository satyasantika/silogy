<?php

namespace App\Modules\Kalkulasi\Services;

use App\Modules\Kalkulasi\Models\HasilCpmk;
use App\Modules\Kalkulasi\Models\HasilSubcpmk;
use App\Modules\Kelas\Models\KelasMk;
use App\Modules\Kelas\Models\KelasMkMahasiswa;
use App\Modules\MK\Models\Cpmk;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CpmkCalculator
{
    public function calculate(string $kelasMkId): void
    {
        $kelasMk = KelasMk::query()
            ->with('mkUnit')
            ->findOrFail($kelasMkId);

        $mkId = $kelasMk->mkUnit?->mk_id;

        if ($mkId === null) {
            return;
        }

        $kmmIds = KelasMkMahasiswa::query()
            ->where('kelas_mk_id', $kelasMkId)
            ->pluck('id');

        if ($kmmIds->isEmpty()) {
            return;
        }

        $cpmkIds = Cpmk::query()
            ->where('mk_id', $mkId)
            ->pluck('id');

        if ($cpmkIds->isEmpty()) {
            return;
        }

        $rataRata = HasilSubcpmk::query()
            ->join('subcpmk', 'subcpmk.id', '=', 'hasil_subcpmk.subcpmk_id')
            ->join('mk_cpmk', 'mk_cpmk.id', '=', 'subcpmk.mk_cpmk_id')
            ->whereIn('hasil_subcpmk.kelas_mk_mahasiswa_id', $kmmIds)
            ->whereIn('mk_cpmk.cpmk_id', $cpmkIds)
            ->whereNotNull('hasil_subcpmk.nilai_akhir')
            ->groupBy('mk_cpmk.cpmk_id', 'hasil_subcpmk.kelas_mk_mahasiswa_id')
            ->select([
                'mk_cpmk.cpmk_id',
                'hasil_subcpmk.kelas_mk_mahasiswa_id',
                DB::raw('AVG(hasil_subcpmk.nilai_akhir) as nilai_akhir'),
            ])
            ->get();

        if ($rataRata->isEmpty()) {
            return;
        }

        $existing = HasilCpmk::query()
            ->where('kelas_mk_id', $kelasMkId)
            ->get()
            ->keyBy(fn (HasilCpmk $hasil): string => $hasil->cpmk_id.'|'.$hasil->kelas_mk_mahasiswa_id);

        $now = now();
        $rows = [];

        foreach ($rataRata as $baris) {
            $key = $baris->cpmk_id.'|'.$baris->kelas_mk_mahasiswa_id;
            $hasil = $existing->get($key);

            $rows[] = [
                'id' => $hasil?->id ?? (string) Str::uuid(),
                'cpmk_id' => $baris->cpmk_id,
                'kelas_mk_mahasiswa_id' => $baris->kelas_mk_mahasiswa_id,
                'kelas_mk_id' => $kelasMkId,
                'nilai_akhir' => round((float) $baris->nilai_akhir, 2),
                'created_at' => $hasil?->created_at ?? $now,
                'updated_at' => $now,
            ];
        }

        HasilCpmk::query()->upsert(
            $rows,
            ['cpmk_id', 'kelas_mk_mahasiswa_id'],
            ['nilai_akhir', 'kelas_mk_id', 'updated_at'],
        );
    }
}
